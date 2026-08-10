import Cocoa
import FlutterMacOS
import UserNotifications
import UniformTypeIdentifiers

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
    installWindowDragHandle()

    RegisterGeneratedPlugins(registry: flutterViewController)
    setupNativeNotificationsChannel(flutterViewController)

    super.awakeFromNib()
  }

  private func installWindowDragHandle() {
    guard let contentView = self.contentView else {
      return
    }

    let dragHandle = WindowDragHandleView()
    dragHandle.translatesAutoresizingMaskIntoConstraints = false
    dragHandle.wantsLayer = true
    dragHandle.layer?.backgroundColor = NSColor.clear.cgColor
    dragHandle.toolTip = "Drag KIUQ SYSTEM window"

    contentView.addSubview(dragHandle, positioned: .above, relativeTo: nil)
    NSLayoutConstraint.activate([
      dragHandle.topAnchor.constraint(equalTo: contentView.topAnchor),
      dragHandle.centerXAnchor.constraint(equalTo: contentView.centerXAnchor),
      dragHandle.widthAnchor.constraint(equalToConstant: 360),
      dragHandle.heightAnchor.constraint(equalToConstant: 54)
    ])
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
    let avatarUrl = args["avatarUrl"] as? String ?? ""
    let soundUrl = args["soundUrl"] as? String ?? ""

    UNUserNotificationCenter.current().getNotificationSettings { settings in
      if settings.authorizationStatus == .authorized || settings.authorizationStatus == .provisional {
        self.showUserNotification(
          identifier: identifier,
          title: title,
          subtitle: subtitle,
          body: body,
          link: link,
          avatarUrl: avatarUrl,
          soundUrl: soundUrl,
          result: result
        )
      } else {
        self.showLegacyNotification(
          identifier: identifier,
          title: title,
          subtitle: subtitle,
          body: body,
          link: link,
          avatarUrl: avatarUrl,
          soundUrl: soundUrl
        )
        DispatchQueue.main.async {
          result(true)
        }
      }
    }
  }

  private func getSoundsDirectory() -> URL {
    let fileManager = FileManager.default
    let libraryUrl = fileManager.urls(for: .libraryDirectory, in: .userDomainMask).first!
    let soundsUrl = libraryUrl.appendingPathComponent("Sounds")
    if !fileManager.fileExists(atPath: soundsUrl.path) {
        try? fileManager.createDirectory(at: soundsUrl, withIntermediateDirectories: true, attributes: nil)
    }
    return soundsUrl
  }

  private func prepareNotificationSound(from urlString: String, completion: @escaping (UNNotificationSound?) -> Void) {
    guard let url = URL(string: urlString), !urlString.isEmpty else {
        completion(.default)
        return
    }
    let fileName = url.lastPathComponent
    let soundsDir = getSoundsDirectory()
    let fileUrl = soundsDir.appendingPathComponent(fileName)
    
    if FileManager.default.fileExists(atPath: fileUrl.path) {
        completion(UNNotificationSound(named: UNNotificationSoundName(rawValue: fileName)))
        return
    }
    
    URLSession.shared.dataTask(with: url) { data, response, error in
        if let data = data {
            try? data.write(to: fileUrl, options: [.atomic])
            completion(UNNotificationSound(named: UNNotificationSoundName(rawValue: fileName)))
        } else {
            completion(.default)
        }
    }.resume()
  }

  private func showUserNotification(
    identifier: String,
    title: String,
    subtitle: String,
    body: String,
    link: String,
    avatarUrl: String,
    soundUrl: String,
    result: @escaping FlutterResult
  ) {
    let content = UNMutableNotificationContent()
    content.title = title
    content.subtitle = subtitle
    content.body = body
    content.userInfo = ["link": link]

    prepareNotificationSound(from: soundUrl) { sound in
      content.sound = sound
      
      self.makeNotificationAttachment(from: avatarUrl) { attachment in
        if let attachment = attachment {
          content.attachments = [attachment]
        }

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
                link: link,
                avatarUrl: avatarUrl,
                soundUrl: soundUrl
              )
              result(true)
            } else {
              NSLog("DGT native notification scheduled: %@", identifier)
              result(true)
            }
          }
        }
      }
    }
  }

  private func showLegacyNotification(
    identifier: String,
    title: String,
    subtitle: String,
    body: String,
    link: String,
    avatarUrl: String,
    soundUrl: String
  ) {
    let notification = NSUserNotification()
    notification.identifier = identifier
    notification.title = title
    notification.subtitle = subtitle
    notification.informativeText = body
    notification.soundName = NSUserNotificationDefaultSoundName
    notification.userInfo = ["link": link]
    if let image = legacyNotificationImage(from: avatarUrl) {
      notification.contentImage = image
    }
    NSUserNotificationCenter.default.deliver(notification)
    NSLog("DGT legacy notification delivered: %@", identifier)
  }

  private func makeNotificationAttachment(
    from rawValue: String,
    completion: @escaping (UNNotificationAttachment?) -> Void
  ) {
    let trimmed = rawValue.trimmingCharacters(in: .whitespacesAndNewlines)
    guard !trimmed.isEmpty else {
      completion(nil)
      return
    }

    if trimmed.hasPrefix("data:image/") {
      completion(makeAttachmentFromDataUri(trimmed))
      return
    }

    guard let url = URL(string: trimmed), ["http", "https"].contains(url.scheme?.lowercased()) else {
      completion(nil)
      return
    }

    URLSession.shared.dataTask(with: url) { data, response, _ in
      guard let data = data, !data.isEmpty else {
        completion(nil)
        return
      }

      let mimeType = (response as? HTTPURLResponse)?.mimeType
      completion(self.makeAttachment(from: data, mimeType: mimeType, suggestedExtension: url.pathExtension))
    }.resume()
  }

  private func makeAttachmentFromDataUri(_ dataUri: String) -> UNNotificationAttachment? {
    let parts = dataUri.split(separator: ",", maxSplits: 1).map(String.init)
    guard parts.count == 2, let data = Data(base64Encoded: parts[1]) else {
      return nil
    }

    let mimeType = parts[0]
      .replacingOccurrences(of: "data:", with: "")
      .components(separatedBy: ";")
      .first

    return makeAttachment(from: data, mimeType: mimeType, suggestedExtension: nil)
  }

  private func makeAttachment(
    from data: Data,
    mimeType: String?,
    suggestedExtension: String?
  ) -> UNNotificationAttachment? {
    let ext = preferredImageExtension(mimeType: mimeType, suggestedExtension: suggestedExtension)
    let fileUrl = URL(fileURLWithPath: NSTemporaryDirectory())
      .appendingPathComponent("kiuq-notification-avatar-\(UUID().uuidString).\(ext)")

    do {
      try data.write(to: fileUrl, options: [.atomic])
      return try UNNotificationAttachment(identifier: "kiuq-user-profile", url: fileUrl)
    } catch {
      NSLog("DGT notification avatar attachment error: %@", error.localizedDescription)
      return nil
    }
  }

  private func preferredImageExtension(mimeType: String?, suggestedExtension: String?) -> String {
    let ext = (suggestedExtension ?? "").lowercased()
    if ["png", "jpg", "jpeg", "gif", "heic", "webp"].contains(ext) {
      return ext == "jpg" ? "jpeg" : ext
    }

    switch mimeType?.lowercased() {
    case "image/png":
      return "png"
    case "image/gif":
      return "gif"
    case "image/heic":
      return "heic"
    case "image/webp":
      return "webp"
    default:
      return "jpeg"
    }
  }

  private func legacyNotificationImage(from rawValue: String) -> NSImage? {
    let trimmed = rawValue.trimmingCharacters(in: .whitespacesAndNewlines)
    guard !trimmed.isEmpty else {
      return nil
    }

    if trimmed.hasPrefix("data:image/") {
      let parts = trimmed.split(separator: ",", maxSplits: 1).map(String.init)
      guard parts.count == 2, let data = Data(base64Encoded: parts[1]) else {
        return nil
      }
      return NSImage(data: data)
    }

    guard let url = URL(string: trimmed), let data = try? Data(contentsOf: url) else {
      return nil
    }

    return NSImage(data: data)
  }
}

final class WindowDragHandleView: NSView {
  override var acceptsFirstResponder: Bool {
    true
  }

  override func mouseDown(with event: NSEvent) {
    if event.clickCount == 2 {
      window?.performZoom(nil)
      return
    }

    window?.performDrag(with: event)
  }
}
