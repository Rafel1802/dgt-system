import AVFoundation
import CallKit
import Flutter
import Network
import QuickLook
import UIKit
import UserNotifications
import WebKit

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  private let iosBridge = KiuqIosBridgeController()

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    iosBridge.configureNotificationCenter()
    application.setMinimumBackgroundFetchInterval(UIApplication.backgroundFetchIntervalMinimum)
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

  override func applicationDidEnterBackground(_ application: UIApplication) {
    super.applicationDidEnterBackground(application)
    iosBridge.onAppDidEnterBackground(application)
  }

  override func applicationWillEnterForeground(_ application: UIApplication) {
    super.applicationWillEnterForeground(application)
    iosBridge.onAppWillEnterForeground(application)
  }

  override func application(
    _ application: UIApplication,
    performFetchWithCompletionHandler completionHandler: @escaping (UIBackgroundFetchResult) -> Void
  ) {
    iosBridge.performBackgroundNotificationPoll { hasNewData in
      completionHandler(hasNewData ? .newData : .noData)
    }
  }
}

/// Native iOS capabilities coordinator for KIUQ SYSTEM:
/// - Native Share Sheet (`UIActivityViewController`) with iPad popover anchor safety
/// - Native QuickLook document/PDF/image viewer (`QLPreviewController`)
/// - Real-time connectivity monitor (`NWPathMonitor`)
/// - User-consented local notifications (`UNUserNotificationCenter`) for Pusher events
/// - Native camera/microphone authorization check (`AVCaptureDevice`)
/// - Native WebRTC CallKit (`CXProvider` / `CXCallController`) & `AVAudioSession` routing
final class KiuqIosBridgeController: NSObject, QLPreviewControllerDataSource, UNUserNotificationCenterDelegate, CXProviderDelegate {
  private var channel: FlutterMethodChannel?
  private var pathMonitor: NWPathMonitor?
  private let monitorQueue = DispatchQueue(label: "com.kiuqsystem.network.monitor", qos: .utility)
  private var isNetworkOnline: Bool = true
  private var connectionType: String = "wifi"
  private var previewFileURL: URL?
  private var previewTitle: String?
  private var backgroundTaskId: UIBackgroundTaskIdentifier = .invalid
  private var backgroundPollTimer: Timer?
  private var bgBaseUrl: String = UserDefaults.standard.string(forKey: "kiuq_bg_base_url") ?? "https://lightcyan-weasel-711536.hostingersite.com"
  private var isBgAuthenticated: Bool = UserDefaults.standard.bool(forKey: "kiuq_bg_authenticated")
  private var lastKnownChatUnreadCount: Int = UserDefaults.standard.integer(forKey: "kiuq_bg_last_chat_unread")

  private lazy var callController = CXCallController()
  private lazy var callKitProvider: CXProvider = {
    let config: CXProviderConfiguration
    if #available(iOS 14.0, *) {
      config = CXProviderConfiguration()
    } else {
      config = CXProviderConfiguration(localizedName: "KIUQ SYSTEM")
    }
    config.MaximumCallGroups_Safe(1)
    config.maximumCallsPerCallGroup = 1
    config.supportsVideo = true
    config.supportedHandleTypes = [.generic]
    config.includesCallsInRecents = false
    let provider = CXProvider(configuration: config)
    provider.setDelegate(self, queue: nil)
    return provider
  }()
  private var activeCallUUIDs: [Int: UUID] = [:]

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

    case "setBadgeCount":
      let count = (args["count"] as? Int) ?? 0
      DispatchQueue.main.async {
        UIApplication.shared.applicationIconBadgeNumber = max(0, count)
        result(true)
      }

    case "configureBackgroundPolling":
      if let baseUrl = args["baseUrl"] as? String, !baseUrl.isEmpty {
        self.bgBaseUrl = baseUrl.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        UserDefaults.standard.set(self.bgBaseUrl, forKey: "kiuq_bg_base_url")
      }
      if let authed = args["authenticated"] as? Bool {
        self.isBgAuthenticated = authed
        UserDefaults.standard.set(authed, forKey: "kiuq_bg_authenticated")
      }
      result(true)

    case "requestMediaPermission":
      let kind = (args["kind"] as? String) ?? "camera"
      requestMediaAccess(kind: kind, result: result)

    case "configureCallAudio":
      let isVideo = (args["isVideo"] as? Bool) ?? false
      let speakerOn = (args["speakerOn"] as? Bool) ?? isVideo
      configureCallAudioSession(isVideo: isVideo, speakerOn: speakerOn, result: result)

    case "setSpeakerphone":
      let enabled = (args["enabled"] as? Bool) ?? true
      setSpeakerphoneEnabled(enabled: enabled, result: result)

    case "deactivateCallAudio":
      deactivateCallAudioSession(result: result)

    case "reportIncomingCallKit":
      reportIncomingCallKit(args: args, result: result)

    case "startOutgoingCallKit":
      startOutgoingCallKit(args: args, result: result)

    case "reportCallConnected":
      reportCallConnected(args: args, result: result)

    case "endCallKit":
      endCallKit(args: args, result: result)

    default:
      result(FlutterMethodNotImplemented)
    }
  }

  // MARK: - AVAudioSession & CallKit

  private func configureCallAudioSession(isVideo: Bool, speakerOn: Bool, result: @escaping FlutterResult) {
    let session = AVAudioSession.sharedInstance()
    do {
      var options: AVAudioSession.CategoryOptions = [.allowBluetooth, .allowBluetoothA2DP]
      if speakerOn || isVideo {
        options.insert(.defaultToSpeaker)
      }
      try session.setCategory(
        .playAndRecord,
        mode: isVideo ? .videoChat : .voiceChat,
        options: options
      )
      try session.setActive(true)
      try session.overrideOutputAudioPort(speakerOn ? .speaker : .none)
      result(true)
    } catch {
      result(false)
    }
  }

  private func setSpeakerphoneEnabled(enabled: Bool, result: @escaping FlutterResult) {
    let session = AVAudioSession.sharedInstance()
    do {
      try session.overrideOutputAudioPort(enabled ? .speaker : .none)
      result(true)
    } catch {
      result(false)
    }
  }

  private func deactivateCallAudioSession(result: @escaping FlutterResult) {
    let session = AVAudioSession.sharedInstance()
    do {
      try session.overrideOutputAudioPort(.none)
      try session.setActive(false, options: .notifyOthersOnDeactivation)
      result(true)
    } catch {
      result(false)
    }
  }

  private func reportIncomingCallKit(args: [String: Any], result: @escaping FlutterResult) {
    #if targetEnvironment(simulator)
    // CallKit incoming call UI is unsupported on Simulator; use in-app Liquid Glass incoming UI seamlessly.
    result(false)
    #else
    let callId = (args["callId"] as? Int) ?? 0
    let callerName = (args["callerName"] as? String) ?? "Team Member"
    let isVideo = (args["isVideo"] as? Bool) ?? false
    guard callId > 0 else {
      result(false)
      return
    }

    let uuid = activeCallUUIDs[callId] ?? UUID()
    activeCallUUIDs[callId] = uuid

    let update = CXCallUpdate()
    update.remoteHandle = CXHandle(type: .generic, value: callerName)
    update.localizedCallerName = callerName
    update.hasVideo = isVideo
    update.supportsHolding = false
    update.supportsGrouping = false
    update.supportsUngrouping = false
    update.supportsDTMF = false

    callKitProvider.reportNewIncomingCall(with: uuid, update: update) { error in
      DispatchQueue.main.async {
        result(error == nil)
      }
    }
    #endif
  }

  private func startOutgoingCallKit(args: [String: Any], result: @escaping FlutterResult) {
    #if targetEnvironment(simulator)
    result(false)
    #else
    let callId = (args["callId"] as? Int) ?? 0
    let calleeName = (args["calleeName"] as? String) ?? "Team Member"
    let isVideo = (args["isVideo"] as? Bool) ?? false
    guard callId > 0 else {
      result(false)
      return
    }

    let uuid = activeCallUUIDs[callId] ?? UUID()
    activeCallUUIDs[callId] = uuid

    let handle = CXHandle(type: .generic, value: calleeName)
    let startAction = CXStartCallAction(call: uuid, handle: handle)
    startAction.isVideo = isVideo
    startAction.contactIdentifier = calleeName

    let transaction = CXTransaction(action: startAction)
    callController.request(transaction) { [weak self] error in
      if error == nil {
        self?.callKitProvider.reportOutgoingCall(with: uuid, startedConnectingAt: Date())
      }
      DispatchQueue.main.async {
        result(error == nil)
      }
    }
    #endif
  }

  private func reportCallConnected(args: [String: Any], result: @escaping FlutterResult) {
    let callId = (args["callId"] as? Int) ?? 0
    if let uuid = activeCallUUIDs[callId] {
      callKitProvider.reportOutgoingCall(with: uuid, connectedAt: Date())
    }
    result(true)
  }

  private func endCallKit(args: [String: Any], result: @escaping FlutterResult) {
    let callId = (args["callId"] as? Int) ?? 0
    if callId > 0, let uuid = activeCallUUIDs.removeValue(forKey: callId) {
      let endAction = CXEndCallAction(call: uuid)
      callController.request(CXTransaction(action: endAction)) { _ in }
    } else {
      for (_, uuid) in activeCallUUIDs {
        let endAction = CXEndCallAction(call: uuid)
        callController.request(CXTransaction(action: endAction)) { _ in }
      }
      activeCallUUIDs.removeAll()
    }
    result(true)
  }

  func providerDidReset(_ provider: CXProvider) {
    activeCallUUIDs.removeAll()
  }

  func provider(_ provider: CXProvider, perform action: CXStartCallAction) {
    let session = AVAudioSession.sharedInstance()
    do {
      var options: AVAudioSession.CategoryOptions = [.allowBluetooth, .allowBluetoothA2DP]
      if action.isVideo {
        options.insert(.defaultToSpeaker)
      }
      try session.setCategory(
        .playAndRecord,
        mode: action.isVideo ? .videoChat : .voiceChat,
        options: options
      )
    } catch {}
    provider.reportOutgoingCall(with: action.callUUID, startedConnectingAt: Date())
    action.fulfill()
  }

  func provider(_ provider: CXProvider, didActivate audioSession: AVAudioSession) {
    do {
      try audioSession.setActive(true)
    } catch {}
  }

  func provider(_ provider: CXProvider, perform action: CXAnswerCallAction) {
    let matchedId = activeCallUUIDs.first(where: { $0.value == action.callUUID })?.key ?? 0
    DispatchQueue.main.async {
      self.channel?.invokeMethod("onCallKitAction", arguments: [
        "action": "accept",
        "callId": matchedId,
      ])
    }
    action.fulfill()
  }

  func provider(_ provider: CXProvider, perform action: CXEndCallAction) {
    let matchedId = activeCallUUIDs.first(where: { $0.value == action.callUUID })?.key ?? 0
    if matchedId > 0 {
      activeCallUUIDs.removeValue(forKey: matchedId)
    }
    DispatchQueue.main.async {
      self.channel?.invokeMethod("onCallKitAction", arguments: [
        "action": "end",
        "callId": matchedId,
      ])
    }
    action.fulfill()
  }

  func provider(_ provider: CXProvider, perform action: CXSetMutedCallAction) {
    let matchedId = activeCallUUIDs.first(where: { $0.value == action.callUUID })?.key ?? 0
    DispatchQueue.main.async {
      self.channel?.invokeMethod("onCallKitAction", arguments: [
        "action": "mute",
        "callId": matchedId,
        "muted": action.isMuted,
      ])
    }
    action.fulfill()
  }

  // MARK: - View Controller Helpers

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

  // MARK: - Local & Background Notifications (Facebook/Messenger Style Background Delivery)

  func onAppDidEnterBackground(_ application: UIApplication) {
    guard isBgAuthenticated else { return }
    endBackgroundPollingTask(application)

    backgroundTaskId = application.beginBackgroundTask(withName: "KiuqBackgroundNotificationSync") { [weak self] in
      self?.endBackgroundPollingTask(application)
    }

    // Immediate poll on entering background + periodic 10s poll while background execution time remains
    performBackgroundNotificationPoll { _ in }
    DispatchQueue.main.async { [weak self] in
      self?.backgroundPollTimer?.invalidate()
      self?.backgroundPollTimer = Timer.scheduledTimer(withTimeInterval: 10.0, repeats: true) { [weak self] _ in
        guard let self = self else { return }
        if application.backgroundTimeRemaining < 5.0 {
          self.endBackgroundPollingTask(application)
          return
        }
        self.performBackgroundNotificationPoll { _ in }
      }
    }
  }

  func onAppWillEnterForeground(_ application: UIApplication) {
    endBackgroundPollingTask(application)
  }

  private func endBackgroundPollingTask(_ application: UIApplication) {
    backgroundPollTimer?.invalidate()
    backgroundPollTimer = nil
    if backgroundTaskId != .invalid {
      application.endBackgroundTask(backgroundTaskId)
      backgroundTaskId = .invalid
    }
  }

  func performBackgroundNotificationPoll(completion: @escaping (Bool) -> Void) {
    guard isBgAuthenticated, isNetworkOnline, let base = URL(string: bgBaseUrl) else {
      completion(false)
      return
    }

    DispatchQueue.main.async {
      WKWebsiteDataStore.default().httpCookieStore.getAllCookies { [weak self] cookies in
        guard let self = self else {
          completion(false)
          return
        }
        let host = base.host ?? ""
        let matchingCookies = cookies.filter { cookie in
          let domain = cookie.domain.trimmingCharacters(in: CharacterSet(charactersIn: "."))
          return host == domain || host.hasSuffix("." + domain)
        }
        guard !matchingCookies.isEmpty else {
          completion(false)
          return
        }

        let cookieHeader = HTTPCookie.requestHeaderFields(with: matchingCookies)
        let group = DispatchGroup()
        var foundNew = false
        var totalUnreadBadge = 0

        // 1) Poll /notifications
        if let notifUrl = URL(string: self.bgBaseUrl + "/notifications") {
          var req = URLRequest(url: notifUrl, cachePolicy: .reloadIgnoringLocalCacheData, timeoutInterval: 8.0)
          req.setValue("application/json", forHTTPHeaderField: "Accept")
          req.setValue("XMLHttpRequest", forHTTPHeaderField: "X-Requested-With")
          for (k, v) in cookieHeader { req.setValue(v, forHTTPHeaderField: k) }

          group.enter()
          URLSession.shared.dataTask(with: req) { data, response, _ in
            defer { group.leave() }
            guard let http = response as? HTTPURLResponse, http.statusCode == 200,
                  let data = data,
                  let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else { return }

            let unreadCount = (json["unread_count"] as? Int) ?? 0
            totalUnreadBadge += unreadCount

            guard let list = json["notifications"] as? [[String: Any]] else { return }
            var seenIds = UserDefaults.standard.stringArray(forKey: "kiuq_bg_seen_notif_ids") ?? []

            for item in list.prefix(5) {
              if item["read_at"] is String { continue }
              let rawId = String(describing: item["id"] ?? "")
              if rawId.isEmpty || seenIds.contains(rawId) { continue }
              seenIds.append(rawId)
              foundNew = true

              let payload = (item["data"] as? [String: Any]) ?? item
              let actor = (payload["actor_name"] as? String) ?? (payload["title"] as? String) ?? "KIUQ SYSTEM"
              let desc = (payload["description"] as? String) ?? (payload["message"] as? String) ?? (payload["body"] as? String) ?? "New activity update"
              let cleanDesc = desc.replacingOccurrences(of: "**", with: "")
              let link = (payload["link"] as? String) ?? "/dashboard"
              let fullUrl = link.hasPrefix("http") ? link : (self.bgBaseUrl + (link.hasPrefix("/") ? link : "/" + link))

              self.deliverLocalNotification(
                args: [
                  "id": "notif-\(rawId)",
                  "title": actor,
                  "body": cleanDesc,
                  "url": fullUrl,
                ],
                result: { _ in }
              )
            }

            if seenIds.count > 300 {
              seenIds = Array(seenIds.suffix(300))
            }
            UserDefaults.standard.set(seenIds, forKey: "kiuq_bg_seen_notif_ids")
          }.resume()
        }

        // 2) Poll /chat/unread-count
        if let chatUrl = URL(string: self.bgBaseUrl + "/chat/unread-count") {
          var req = URLRequest(url: chatUrl, cachePolicy: .reloadIgnoringLocalCacheData, timeoutInterval: 8.0)
          req.setValue("application/json", forHTTPHeaderField: "Accept")
          req.setValue("XMLHttpRequest", forHTTPHeaderField: "X-Requested-With")
          for (k, v) in cookieHeader { req.setValue(v, forHTTPHeaderField: k) }

          group.enter()
          URLSession.shared.dataTask(with: req) { data, response, _ in
            defer { group.leave() }
            guard let http = response as? HTTPURLResponse, http.statusCode == 200,
                  let data = data,
                  let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                  let chatUnread = json["unread_count"] as? Int else { return }

            totalUnreadBadge += chatUnread
            if chatUnread > self.lastKnownChatUnreadCount {
              let diff = chatUnread - self.lastKnownChatUnreadCount
              foundNew = true
              self.deliverLocalNotification(
                args: [
                  "id": "chat-bg-\(chatUnread)",
                  "title": "DGT Team Chat",
                  "body": diff == 1 ? "You have a new unread message" : "You have \(chatUnread) unread messages",
                  "url": self.bgBaseUrl + "/chat",
                ],
                result: { _ in }
              )
            }
            self.lastKnownChatUnreadCount = chatUnread
            UserDefaults.standard.set(chatUnread, forKey: "kiuq_bg_last_chat_unread")
          }.resume()
        }

        group.notify(queue: .main) {
          UIApplication.shared.applicationIconBadgeNumber = max(0, totalUnreadBadge)
          completion(foundNew)
        }
      }
    }
  }

  private func deliverLocalNotification(args: [String: Any], result: @escaping FlutterResult) {
    let notifId = (args["id"] as? String)?.trimmingCharacters(in: .whitespacesAndNewlines) ?? UUID().uuidString
    let title = (args["title"] as? String)?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
    let subtitle = (args["subtitle"] as? String)?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
    let body = ((args["body"] as? String) ?? (args["message"] as? String))?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
    let targetUrl = ((args["url"] as? String) ?? (args["link"] as? String)) ?? ""
    let badgeCount = args["badgeCount"] as? Int

    guard !title.isEmpty || !body.isEmpty else {
      result(false)
      return
    }

    // Track in seen IDs so background poll doesn't re-fire a notification already shown via WebSocket
    if notifId.hasPrefix("notif-") {
      let rawId = String(notifId.dropFirst(6))
      var seenIds = UserDefaults.standard.stringArray(forKey: "kiuq_bg_seen_notif_ids") ?? []
      if !seenIds.contains(rawId) {
        seenIds.append(rawId)
        UserDefaults.standard.set(seenIds, forKey: "kiuq_bg_seen_notif_ids")
      }
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
      if !subtitle.isEmpty {
        content.subtitle = subtitle
      }
      content.body = body
      content.sound = .default
      if let badge = badgeCount, badge >= 0 {
        content.badge = NSNumber(value: badge)
        DispatchQueue.main.async {
          UIApplication.shared.applicationIconBadgeNumber = badge
        }
      }
      if !targetUrl.isEmpty {
        content.userInfo = ["url": targetUrl]
      }

      let request = UNNotificationRequest(
        identifier: "kiuq.\(notifId)",
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

private extension CXProviderConfiguration {
  func MaximumCallGroups_Safe(_ count: Int) {
    self.maximumCallGroups = count
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
