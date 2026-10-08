/**
 * ============================================================================
 * GOOGLE APPS SCRIPT: AUTOMATIC STAFF KPI PDF UPLOADER TO GOOGLE DRIVE
 * ============================================================================
 * 
 * Target Parent Folder:
 * https://drive.google.com/drive/folders/1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi
 * Folder ID: 1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi
 * 
 * Instructions to Deploy:
 * 1. Open https://script.google.com/ and click "New Project"
 * 2. Paste this entire file into Code.gs
 * 3. Click "Deploy" -> "New deployment"
 * 4. Select type: "Web app"
 * 5. Configuration:
 *    - Description: "Staff KPI Google Drive Auto-Uploader"
 *    - Execute as: "Me (your google account)"
 *    - Who has access: "Anyone"
 * 6. Click "Deploy" and authorize access to Google Drive
 * 7. Deployed Web App URL:
 *    https://script.google.com/macros/s/AKfycbxXXOumYYCzercvaTZwu8maugr8FDkHDudUQ5kTf4JWIUY3GqRzqJJgPw27zFEFPvnG/exec
 * 8. Configured in your Laravel .env file:
 *    GOOGLE_KPI_APPS_SCRIPT_URL=https://script.google.com/macros/s/AKfycbxXXOumYYCzercvaTZwu8maugr8FDkHDudUQ5kTf4JWIUY3GqRzqJJgPw27zFEFPvnG/exec
 *    GOOGLE_KPI_DRIVE_FOLDER_ID=1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi
 *    GOOGLE_KPI_API_SECRET=kpi-drive-sync-secret-2026
 * ============================================================================
 */

var TARGET_ROOT_FOLDER_ID = "1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi";
var EXPECTED_API_SECRET = "kpi-drive-sync-secret-2026";

/**
 * Handle incoming POST requests from Laravel system
 */
function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return jsonResponse({
        status: "error",
        message: "No POST body received."
      }, 400);
    }

    var data = JSON.parse(e.postData.contents);

    // 1. Optional API Secret validation
    if (EXPECTED_API_SECRET && data.secret && data.secret !== EXPECTED_API_SECRET) {
      return jsonResponse({
        status: "error",
        message: "Invalid API secret."
      }, 403);
    }

    var folderId = data.folder_id || data.folderId || TARGET_ROOT_FOLDER_ID;
    var year = String(data.year || new Date().getFullYear()).trim();
    var month = String(data.month || getMonthName(new Date().getMonth() + 1)).trim();
    var fileName = (data.file_name || data.fileName || ("KPI_" + year + "_" + month + ".pdf")).trim();
    var fileDataBase64 = data.file_data || data.fileData;
    var mimeType = data.mime_type || data.mimeType || "application/pdf";

    if (!fileDataBase64) {
      return jsonResponse({
        status: "error",
        message: "Missing 'file_data' base64 payload."
      }, 400);
    }

    // 2. Open Root Folder (1sQiq4-jtjsRmEBfJr6ofZkHXuHZuLhTi)
    var rootFolder;
    try {
      rootFolder = DriveApp.getFolderById(folderId);
    } catch (fErr) {
      return jsonResponse({
        status: "error",
        message: "Cannot open root Google Drive folder with ID: " + folderId + ". Details: " + fErr.toString()
      }, 404);
    }

    // 3. Find or Auto-Create Year Folder (e.g. 2026, 2027, 2028...)
    var yearFolder = getOrCreateFolder(rootFolder, year);

    // 4. Find or Auto-Create Month Folder inside Year Folder (e.g. September)
    var monthFolder = getOrCreateFolder(yearFolder, month);

    // 5. Decode Base64 PDF Data
    var decodedBytes = Utilities.base64Decode(fileDataBase64);
    var blob = Utilities.newBlob(decodedBytes, mimeType, fileName);

    // 6. If file with exact same name already exists in this month folder, replace/trash old version
    var existingFiles = monthFolder.getFilesByName(fileName);
    while (existingFiles.hasNext()) {
      var oldFile = existingFiles.next();
      try {
        oldFile.setTrashed(true);
      } catch (tErr) {
        Logger.log("Could not trash old file: " + tErr);
      }
    }

    // 7. Create New File in the Month Folder
    var createdFile = monthFolder.createFile(blob);
    createdFile.setDescription("Staff KPI Evaluation Certificate - " + month + " " + year);

    // Allow anyone with link to view (optional, ensures seamless preview)
    try {
      createdFile.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
    } catch (shareErr) {
      Logger.log("Share permission note: " + shareErr);
    }

    return jsonResponse({
      status: "success",
      message: "KPI PDF uploaded successfully to Google Drive folder " + year + "/" + month + "!",
      file_id: createdFile.getId(),
      file_url: createdFile.getUrl(),
      download_url: createdFile.getDownloadUrl(),
      year_folder_name: year,
      year_folder_id: yearFolder.getId(),
      month_folder_name: month,
      month_folder_id: monthFolder.getId(),
      file_name: fileName
    }, 200);

  } catch (error) {
    return jsonResponse({
      status: "error",
      message: "Google Apps Script internal exception: " + error.toString()
    }, 500);
  }
}

/**
 * Get existing subfolder by name or create a new one
 */
function getOrCreateFolder(parentFolder, folderName) {
  var folders = parentFolder.getFoldersByName(folderName);
  if (folders.hasNext()) {
    return folders.next();
  }
  return parentFolder.createFolder(folderName);
}

/**
 * Healthcheck GET request handler
 */
function doGet(e) {
  return jsonResponse({
    status: "ok",
    service: "Staff KPI Google Drive Auto-Uploader",
    target_root_folder_id: TARGET_ROOT_FOLDER_ID,
    target_folder_url: "https://drive.google.com/drive/folders/" + TARGET_ROOT_FOLDER_ID,
    timestamp: new Date().toISOString()
  }, 200);
}

/**
 * Month number to English name helper
 */
function getMonthName(monthNumber) {
  var months = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
  ];
  return months[monthNumber - 1] || "Current Month";
}

/**
 * Return JSON response
 */
function jsonResponse(obj, statusCode) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
