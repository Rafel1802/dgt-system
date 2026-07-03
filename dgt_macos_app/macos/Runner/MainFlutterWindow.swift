import Cocoa
import FlutterMacOS
import UserNotifications

class MainFlutterWindow: NSWindow {
  override func awakeFromNib() {
    let flutterViewController = FlutterViewController()
    let windowFrame = self.frame

    titleVisibility = .hidden
    titlebarAppearsTransparent = true
    styleMask.insert(.fullSizeContentView)
    isMovableByWindowBackground = true
    backgroundColor = NSColor(
      calibratedRed: 244.0 / 255.0,
      green: 247.0 / 255.0,
      blue: 251.0 / 255.0,
      alpha: 1.0
    )

    self.contentViewController = flutterViewController
    self.setFrame(windowFrame, display: true)

    RegisterGeneratedPlugins(registry: flutterViewController)
    setupNativeNotificationsChannel(flutterViewController)

    super.awakeFromNib()
  }

  private func setupNativeNotificationsChannel(_ flutterViewController: FlutterViewController) {
    let channel = FlutterMethodChannel(
      name: "dgt_system/native_notifications",
      binaryMessenger: flutterViewController.engine.binaryMessenger
    )

    channel.setMethodCallHandler { call, result in
      switch call.method {
      case "requestPermission":
        self.requestNotificationPermission(result)
      case "showNotification":
        self.showNativeNotification(call, result)
      default:
        result(FlutterMethodNotImplemented)
      }
    }
  }

  private func requestNotificationPermission(_ result: @escaping FlutterResult) {
    UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound]) { granted, error in
      DispatchQueue.main.async {
        if let error = error {
          NSLog("DGT notification permission error: %@", error.localizedDescription)
          result(FlutterError(code: "permission_error", message: error.localizedDescription, details: nil))
        } else {
          NSLog("DGT notification permission granted: %@", granted ? "true" : "false")
          result(granted)
        }
      }
    }
  }

  private func showNativeNotification(_ call: FlutterMethodCall, _ result: @escaping FlutterResult) {
    guard let args = call.arguments as? [String: Any] else {
      result(FlutterError(code: "invalid_args", message: "Notification payload is missing.", details: nil))
      return
    }

    let requestedIdentifier = args["id"] as? String ?? ""
    let identifier = requestedIdentifier.isEmpty ? UUID().uuidString : requestedIdentifier
    let title = args["title"] as? String ?? "KIUQ SYSTEM"
    let subtitle = args["subtitle"] as? String ?? ""
    let body = args["body"] as? String ?? "New notification"
    let link = args["link"] as? String ?? ""

    UNUserNotificationCenter.current().getNotificationSettings { settings in
      if settings.authorizationStatus == .authorized || settings.authorizationStatus == .provisional {
        self.showUserNotification(
          identifier: identifier,
          title: title,
          subtitle: subtitle,
          body: body,
          link: link,
          result: result
        )
      } else {
        self.showLegacyNotification(
          identifier: identifier,
          title: title,
          subtitle: subtitle,
          body: body,
          link: link
        )
        DispatchQueue.main.async {
          result(true)
        }
      }
    }
  }

  private func showUserNotification(
    identifier: String,
    title: String,
    subtitle: String,
    body: String,
    link: String,
    result: @escaping FlutterResult
  ) {
    let content = UNMutableNotificationContent()
    content.title = title
    content.subtitle = subtitle
    content.body = body
    content.sound = .default
    content.userInfo = ["link": link]

    let request = UNNotificationRequest(
      identifier: identifier,
      content: content,
      trigger: nil
    )

    UNUserNotificationCenter.current().add(request) { error in
      DispatchQueue.main.async {
        if let error = error {
          NSLog("DGT native notification show error: %@", error.localizedDescription)
          self.showLegacyNotification(
            identifier: identifier,
            title: title,
            subtitle: subtitle,
            body: body,
            link: link
          )
          result(true)
        } else {
          NSLog("DGT native notification scheduled: %@", identifier)
          result(true)
        }
      }
    }
  }

  private func showLegacyNotification(
    identifier: String,
    title: String,
    subtitle: String,
    body: String,
    link: String
  ) {
    let notification = NSUserNotification()
    notification.identifier = identifier
    notification.title = title
    notification.subtitle = subtitle
    notification.informativeText = body
    notification.soundName = NSUserNotificationDefaultSoundName
    notification.userInfo = ["link": link]
    NSUserNotificationCenter.default.deliver(notification)
    NSLog("DGT legacy notification delivered: %@", identifier)
  }
}
