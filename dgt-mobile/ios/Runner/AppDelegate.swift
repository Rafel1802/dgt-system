import AVFoundation
import Flutter
import Network
import QuickLook
import UIKit
import UserNotifications

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  private let iosBridge = KiuqIosBridgeController()

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    iosBridge.configureNotificationCenter()
    let launched = super.application(application, didFinishLaunchingWithOptions: launchOptions)
    // Fallback registration if non-implicit window setup is used.
    if !iosBridge.isChannelConfigured,
       let registrar = self.registrar(forPlugin: "KiuqSystemIosBridge") {
      iosBridge.register(with: registrar.messenger())
    }
    return launched
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)
    if let registrar = engineBridge.pluginRegistry.registrar(forPlugin: "KiuqSystemIosBridge") {
      iosBridge.register(with: registrar.messenger())
    }
  }
}

/// Native iOS capabilities coordinator for KIUQ SYSTEM:
/// - Native Share Sheet (`UIActivityViewController`) with iPad popover anchor safety
/// - Native QuickLook document/PDF/image viewer (`QLPreviewController`)
/// - Real-time connectivity monitor (`NWPathMonitor`)
/// - User-consented local notifications (`UNUserNotificationCenter`) for Pusher events
/// - Native camera/microphone authorization check (`AVCaptureDevice`)
final class KiuqIosBridgeController: NSObject, QLPreviewControllerDataSource, UNUserNotificationCenterDelegate {
  private var channel: FlutterMethodChannel?
  private var pathMonitor: NWPathMonitor?
  private let monitorQueue = DispatchQueue(label: "com.kiuqsystem.network.monitor", qos: .utility)
  private var isNetworkOnline: Bool = true
  private var connectionType: String = "wifi"
  private var previewFileURL: URL?
  private var previewTitle: String?

  var isChannelConfigured: Bool {
    return channel != nil
  }

  func configureNotificationCenter() {
    UNUserNotificationCenter.current().delegate = self
  }

  func register(with messenger: FlutterBinaryMessenger) {
    guard channel == nil else { return }
    let methodChannel = FlutterMethodChannel(
      name: "kiuq_system/ios_bridge",
      binaryMessenger: messenger
    )
    methodChannel.setMethodCallHandler { [weak self] call, result in
      self?.handle(call: call, result: result)
    }
    self.channel = methodChannel
    startNetworkMonitoring()
  }

  private func startNetworkMonitoring() {
    guard pathMonitor == nil else { return }
    let monitor = NWPathMonitor()
    monitor.pathUpdateHandler = { [weak self] path in
      guard let self = self else { return }
      let online = path.status == .satisfied
      let type: String
      if path.usesInterfaceType(.wifi) {
        type = "wifi"
      } else if path.usesInterfaceType(.cellular) {
        type = "cellular"
      } else if path.usesInterfaceType(.wiredEthernet) {
        type = "ethernet"
      } else if online {
        type = "other"
      } else {
        type = "none"
      }

      let changed = (online != self.isNetworkOnline) || (type != self.connectionType)
      self.isNetworkOnline = online
      self.connectionType = type

      if changed {
        DispatchQueue.main.async {
          self.channel?.invokeMethod(
            "onConnectivityChanged",
            arguments: [
              "online": online,
              "type": type,
            ]
          )
        }
      }
    }
    monitor.start(queue: monitorQueue)
    self.pathMonitor = monitor
  }

  private func handle(call: FlutterMethodCall, result: @escaping FlutterResult) {
    let args = call.arguments as? [String: Any] ?? [:]

    switch call.method {
    case "checkConnectivity":
      result([
        "online": isNetworkOnline,
        "type": connectionType,
      ])

    case "share":
      presentShareSheet(args: args, result: result)

    case "previewFile":
      presentQuickLook(args: args, result: result)

    case "getNotificationStatus":
      UNUserNotificationCenter.current().getNotificationSettings { settings in
        let status: String
        switch settings.authorizationStatus {
        case .authorized, .provisional, .ephemeral:
          status = "authorized"
        case .denied:
          status = "denied"
        case .notDetermined:
          status = "notDetermined"
        @unknown default:
          status = "notDetermined"
        }
        DispatchQueue.main.async {
          result(status)
        }
      }

    case "requestNotificationPermission":
      UNUserNotificationCenter.current().requestAuthorization(
        options: [.alert, .badge, .sound]
      ) { granted, _ in
        DispatchQueue.main.async {
          result(granted)
        }
      }

    case "showLocalNotification":
      deliverLocalNotification(args: args, result: result)

    case "requestMediaPermission":
      let kind = (args["kind"] as? String) ?? "camera"
      requestMediaAccess(kind: kind, result: result)

    default:
      result(FlutterMethodNotImplemented)
    }
  }

  private func activeTopViewController() -> UIViewController? {
    let scenes = UIApplication.shared.connectedScenes
      .compactMap { $0 as? UIWindowScene }
      .filter { $0.activationState == .foregroundActive || $0.activationState == .foregroundInactive }

    let keyWindow = scenes
      .flatMap { $0.windows }
      .first(where: { $0.isKeyWindow }) ?? scenes.flatMap { $0.windows }.first

    guard var top = keyWindow?.rootViewController else { return nil }
    while let presented = top.presentedViewController, !presented.isBeingDismissed {
      top = presented
    }
    return top
  }

  private func presentShareSheet(args: [String: Any], result: @escaping FlutterResult) {
    DispatchQueue.main.async {
      guard let presenter = self.activeTopViewController() else {
        result(false)
        return
      }

      var items: [Any] = []
      if let filePath = args["filePath"] as? String,
         !filePath.isEmpty,
         FileManager.default.fileExists(atPath: filePath) {
        items.append(URL(fileURLWithPath: filePath))
      } else {
        if let title = args["title"] as? String, !title.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
          items.append(title)
        }
        if let urlString = args["url"] as? String, let shareURL = URL(string: urlString) {
          items.append(shareURL)
        } else if let text = args["text"] as? String, !text.isEmpty {
          items.append(text)
        }
      }

      guard !items.isEmpty else {
        result(false)
        return
      }

      let activityVC = UIActivityViewController(activityItems: items, applicationActivities: nil)
      if let popover = activityVC.popoverPresentationController {
        popover.sourceView = presenter.view
        let bounds = presenter.view.bounds
        popover.sourceRect = CGRect(
          x: bounds.midX - 1,
          y: max(bounds.midY - 40, 44),
          width: 2,
          height: 2
        )
        popover.permittedArrowDirections = []
      }

      presenter.present(activityVC, animated: true) {
        result(true)
      }
    }
  }

  private func presentQuickLook(args: [String: Any], result: @escaping FlutterResult) {
    DispatchQueue.main.async {
      guard let filePath = args["filePath"] as? String,
            !filePath.isEmpty,
            FileManager.default.fileExists(atPath: filePath),
            let presenter = self.activeTopViewController() else {
        result(false)
        return
      }

      self.previewFileURL = URL(fileURLWithPath: filePath)
      self.previewTitle = args["title"] as? String

      let previewController = QLPreviewController()
      previewController.dataSource = self
      previewController.modalPresentationStyle = .fullScreen
      presenter.present(previewController, animated: true) {
        result(true)
      }
    }
  }

  // MARK: - QLPreviewControllerDataSource

  func numberOfPreviewItems(in controller: QLPreviewController) -> Int {
    return previewFileURL == nil ? 0 : 1
  }

  func previewController(_ controller: QLPreviewController, previewItemAt index: Int) -> QLPreviewItem {
    guard let fileURL = previewFileURL else {
      return NSURL(fileURLWithPath: "")
    }
    return KiuqPreviewItem(url: fileURL, title: previewTitle)
  }

  // MARK: - Local Notifications

  private func deliverLocalNotification(args: [String: Any], result: @escaping FlutterResult) {
    let title = (args["title"] as? String)?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
    let body = (args["body"] as? String)?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
    let targetUrl = (args["url"] as? String) ?? ""

    guard !title.isEmpty || !body.isEmpty else {
      result(false)
      return
    }

    UNUserNotificationCenter.current().getNotificationSettings { settings in
      guard settings.authorizationStatus == .authorized ||
            settings.authorizationStatus == .provisional else {
        DispatchQueue.main.async {
          result(false)
        }
        return
      }

      let content = UNMutableNotificationContent()
      content.title = title.isEmpty ? "KIUQ SYSTEM" : title
      content.body = body
      content.sound = .default
      if !targetUrl.isEmpty {
        content.userInfo = ["url": targetUrl]
      }

      let request = UNNotificationRequest(
        identifier: "kiuq.\(UUID().uuidString)",
        content: content,
        trigger: nil
      )

      UNUserNotificationCenter.current().add(request) { error in
        DispatchQueue.main.async {
          result(error == nil)
        }
      }
    }
  }

  func userNotificationCenter(
    _ center: UNUserNotificationCenter,
    willPresent notification: UNNotification,
    withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
  ) {
    if #available(iOS 14.0, *) {
      completionHandler([.banner, .list, .sound])
    } else {
      completionHandler([.alert, .sound])
    }
  }

  func userNotificationCenter(
    _ center: UNUserNotificationCenter,
    didReceive response: UNNotificationResponse,
    withCompletionHandler completionHandler: @escaping () -> Void
  ) {
    if let url = response.notification.request.content.userInfo["url"] as? String,
       !url.isEmpty {
      DispatchQueue.main.async {
        self.channel?.invokeMethod("onNotificationTap", arguments: ["url": url])
      }
    }
    completionHandler()
  }

  // MARK: - Camera & Microphone Consent

  private func requestMediaAccess(kind: String, result: @escaping FlutterResult) {
    let mediaType: AVMediaType = (kind == "microphone") ? .audio : .video
    let status = AVCaptureDevice.authorizationStatus(for: mediaType)
    switch status {
    case .authorized:
      result(true)
    case .denied, .restricted:
      result(false)
    case .notDetermined:
      AVCaptureDevice.requestAccess(for: mediaType) { granted in
        DispatchQueue.main.async {
          result(granted)
        }
      }
    @unknown default:
      result(false)
    }
  }
}

private final class KiuqPreviewItem: NSObject, QLPreviewItem {
  let previewItemURL: URL?
  let previewItemTitle: String?

  init(url: URL, title: String?) {
    self.previewItemURL = url
    self.previewItemTitle = title
    super.init()
  }
}
