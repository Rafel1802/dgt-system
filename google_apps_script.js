/**
 * ═══════════════════════════════════════════════════════════════════════════════
 *  DGT System — Google Blogs Sheet Integration
 *  Google Apps Script Web App  (doPost endpoint)
 *
 *  VERSION: 3.2.0 (Verified against Blog Content Production & Scheduling Plan)
 *
 *  SHEET STRUCTURE:
 *    Tabs: "Sep Blogs", "Oct Blogs", "Nov Blogs", "Dec Blogs", "Blogs"
 *    Row 1: Group Headers: "Class 3th & 4th", "Class 2nd", "Class 1st", "Class 5th", "Class 6th", "Class 7th"
 *    Row 2: Column Headers: "Class", "Doc Link", "Public Link", "Dated", "Website link", "Writer"
 *
 *    Each 7-column class block layout:
 *      +0 = Class        (Col A=0,  H=7,  O=14, V=21, AC=28, AJ=35)
 *      +1 = Doc Link     (Col B=1,  I=8,  P=15, W=22, AD=29, AK=36)  — NEVER modified
 *      +2 = Public Link  (Col C=2,  J=9,  Q=16, X=23, AE=30, AL=37)  — Updated with Blog URL
 *      +3 = Dated        (Col D=3,  K=10, R=17, Y=24, AF=31, AM=38)  — Updated with MM/DD Date
 *      +4 = Website link (Col E=4,  L=11, S=18, Z=25, AG=32, AN=39)  — Preserved
 *      +5 = Writer       (Col F=5,  M=12, T=19, AA=26, AH=33, AO=40) — Preserved
 *      +6 = Spacer / Blank
 * ═══════════════════════════════════════════════════════════════════════════════
 */

// ── ❶  CONFIGURATION ─────────────────────────────────────────────────────────

var API_SECRET = 'b5de36cdc1b83d59def04033ab06c54c06f6f5630c18e33953c86c0cb0135483';

var SPREADSHEET_ID = '1X727wTcYSEdybFppqsoqDPmbt3igZ4a844U6ncZh8JQ';

var DEFAULT_SHEET_NAME = 'Blogs';

var IDEMPOTENCY_CACHE_SIZE = 2000;

// ── ❷  ENTRY POINT ───────────────────────────────────────────────────────────

function doPost(e) {
  var startTime = new Date();

  try {
    // ── Parse request body ──────────────────────────────────────────────────
    if (!e || !e.postData || !e.postData.contents) {
      return buildResponse({ success: false, message: 'Empty request body.' });
    }

    var payload;
    try {
      payload = JSON.parse(e.postData.contents);
    } catch (parseErr) {
      Logger.log('JSON parse error: ' + parseErr.message);
      return buildResponse({ success: false, message: 'Invalid JSON payload: ' + parseErr.message });
    }

    // ── Validate shared secret ──────────────────────────────────────────────
    var receivedSecret = payload.secret || '';
    if (!constantTimeEqual(receivedSecret, API_SECRET)) {
      Logger.log('Unauthorized request — secret mismatch.');
      return buildResponse({ success: false, message: 'Unauthorized.' });
    }

    // ── Extract parameters ──────────────────────────────────────────────────
    var classNum   = String(payload['class']    || '').trim();
    var publicLink = String(payload.public_link || '').trim();
    var dated      = String(payload.date        || '').trim();
    var requestId  = String(payload.request_id  || '').trim();
    var action     = String(payload.action      || '').trim();

    if (action !== 'delete' && action !== 'export_csv') {
      var validationError = validateFields(classNum, publicLink, dated);
      if (validationError) {
        return buildResponse({ success: false, message: validationError });
      }
    }

    var websiteDomain = payload.website || extractDomain(publicLink);
    var normLink = normaliseUrl(publicLink);

    // ── Concurrency Lock ────────────────────────────────────────────────────
    var lock = LockService.getScriptLock();
    try {
      lock.waitLock(30000);
    } catch (lockErr) {
      Logger.log('Lock wait timeout: ' + lockErr.message);
      return buildResponse({ success: false, message: 'Google Sheet is currently busy. Please try again in a few seconds.' });
    }

    try {
      // ── Idempotency Check ─────────────────────────────────────────────────
      if (requestId && action !== 'delete' && action !== 'export_csv') {
        var already = getIdempotencyResult(requestId);
        if (already) {
          Logger.log('Idempotency cache hit: ' + requestId);
          return buildResponse(already);
        }
      }

      // ── Open Spreadsheet ──────────────────────────────────────────────────
      var ss = null;
      try {
        ss = SpreadsheetApp.getActiveSpreadsheet();
      } catch (e) {
        ss = null;
      }

      if (!ss) {
        try {
          ss = SpreadsheetApp.openById(SPREADSHEET_ID);
        } catch (ssErr) {
          Logger.log('openById error: ' + ssErr.message);
          return buildResponse({
            success: false,
            message: 'Cannot access Google Spreadsheet. ' + ssErr.message + 
                     '. Please ensure the Google account deploying this script has Editor access to: ' + 
                     'https://docs.google.com/spreadsheets/d/' + SPREADSHEET_ID + '/edit'
          });
        }
      }

      // ── Select Correct Monthly Sheet Tab ──────────────────────────────────
      var sheet = null;
      var requestedSheetName = String(payload.sheet_tab || payload.sheet_name || '').trim();
      var allSheets = ss.getSheets();

      // 1. If explicit sheet_tab provided, find it
      if (requestedSheetName) {
        sheet = ss.getSheetByName(requestedSheetName);
        if (!sheet) {
          var lowerReq = requestedSheetName.toLowerCase();
          for (var s = 0; s < allSheets.length; s++) {
            if (allSheets[s].getName().trim().toLowerCase() === lowerReq) {
              sheet = allSheets[s];
              break;
            }
          }
        }
      }

      // 2. Derive month from dated parameter
      if (!sheet && dated) {
        var targetMonthNames = [];
        var d = new Date(dated);
        var m = 0;
        if (!isNaN(d.getTime())) {
          m = d.getMonth() + 1;
        } else {
          var mMatch = dated.match(/(\d{1,2})[\/\-](\d{1,2})/);
          if (mMatch) {
            var v1 = parseInt(mMatch[1], 10), v2 = parseInt(mMatch[2], 10);
            m = (v1 >= 9 && v1 <= 12) ? v1 : ((v2 >= 9 && v2 <= 12) ? v2 : v1);
          }
        }

        var monthMap = {
          1:  ['jan blogs', 'january blogs', 'jan'],
          2:  ['feb blogs', 'february blogs', 'feb'],
          3:  ['mar blogs', 'march blogs', 'mar'],
          4:  ['apr blogs', 'april blogs', 'apr'],
          5:  ['may blogs', 'may'],
          6:  ['jun blogs', 'june blogs', 'jun'],
          7:  ['jul blogs', 'july blogs', 'jul'],
          8:  ['aug blogs', 'august blogs', 'aug'],
          9:  ['sep blogs', 'september blogs', 'sep'],
          10: ['oct blogs', 'october blogs', 'oct'],
          11: ['nov blogs', 'november blogs', 'nov'],
          12: ['dec blogs', 'december blogs', 'dec']
        };

        targetMonthNames = monthMap[m] || [];
        if (targetMonthNames.length > 0) {
          for (var tm = 0; tm < targetMonthNames.length; tm++) {
            var tName = targetMonthNames[tm];
            for (var s = 0; s < allSheets.length; s++) {
              var sName = allSheets[s].getName().trim().toLowerCase();
              if (sName === tName || sName.indexOf(tName) !== -1) {
                sheet = allSheets[s];
                break;
              }
            }
            if (sheet) break;
          }
        }
      }

      if (!sheet) {
        sheet = ss.getSheetByName(DEFAULT_SHEET_NAME);
      }
      if (!sheet && allSheets.length > 0) {
        sheet = allSheets[0];
      }

      if (!sheet) {
        return buildResponse({ success: false, message: 'Target sheet tab not found in the spreadsheet.' });
      }

      var sheetName = sheet.getName();

      // ── Read Sheet Data ───────────────────────────────────────────────────
      var lastRow = sheet.getLastRow();
      var lastCol = sheet.getLastColumn();

      if (lastRow < 2) {
        return buildResponse({ success: false, message: 'Google Sheet "' + sheetName + '" does not contain data rows.' });
      }

      var allData = sheet.getRange(1, 1, lastRow, lastCol).getValues();

      // ── Dynamically Locate Class Block & Columns ──────────────────────────
      var block = findClassBlock(allData, classNum, websiteDomain);
      if (!block) {
        return buildResponse({
          success: false,
          message: 'Could not find column block for Class ' + classNum + ' in sheet "' + sheetName + '".'
        });
      }

      var COL_CLASS   = block.colClass;
      var COL_DOC     = block.colDoc;
      var COL_PUBLIC  = block.colPublic;
      var COL_DATED   = block.colDated;
      var COL_WEBSITE = block.colWebsite;
      var dataStartRow = block.dataStartRow; // Usually row index 2 (row 3 of sheet)

      // ── Handle DELETE action ──────────────────────────────────────────────
      if (action === 'delete') {
        var targetRow = -1;

        if (payload.sheet_row) {
          var rowNum = parseInt(payload.sheet_row, 10);
          if (rowNum > dataStartRow && rowNum <= allData.length) {
            targetRow = rowNum;
          }
        }

        if (targetRow === -1 && normLink) {
          for (var r = dataStartRow; r < allData.length; r++) {
            var cellPublic = String(allData[r][COL_PUBLIC] || '').trim();
            if (cellPublic !== '' && normaliseUrl(cellPublic) === normLink) {
              targetRow = r + 1;
              break;
            }
          }
        }

        if (targetRow !== -1) {
          sheet.getRange(targetRow, COL_PUBLIC + 1).setValue('');
          SpreadsheetApp.flush();
          lock.releaseLock();
          return buildResponse({ success: true, message: 'Deleted from Google Sheet.' });
        }

        lock.releaseLock();
        return buildResponse({ success: true, message: 'Link or row not found in Google Sheet.' });
      }

      // ── Handle EXPORT_CSV action ──────────────────────────────────────────
      if (action === 'export_csv') {
        var targetSpreadsheetId = String(payload.spreadsheet_id || '').trim() || SPREADSHEET_ID;
        try {
          var exportSs = (ss && targetSpreadsheetId === SPREADSHEET_ID) ? ss : SpreadsheetApp.openById(targetSpreadsheetId);
          var exportSheetTabName = String(payload.sheet_tab || payload.sheet_name || '').trim();
          var exportSheet = exportSheetTabName ? exportSs.getSheetByName(exportSheetTabName) : null;
          if (!exportSheet) {
            exportSheet = exportSs.getSheetByName(DEFAULT_SHEET_NAME) || exportSs.getSheets()[0];
          }
          var exportData = exportSheet.getDataRange().getValues();
          var csvString = '';
          for (var i = 0; i < exportData.length; i++) {
            var rowString = exportData[i].map(function(cell) {
              var str = String(cell).replace(/"/g, '""');
              return '"' + str + '"';
            }).join(',');
            csvString += rowString + '\n';
          }
          return buildResponse({ success: true, sheet: exportSheet.getName(), csv: csvString });
        } catch (err) {
          return buildResponse({ success: false, message: 'Could not export CSV: ' + err.message });
        }
      }

      // ── Handle SYNC_BLOG_FOLLOW_UP action ──────────────────────────────────
      if (action === 'syncBlogFollowUp') {
        var targetWeb = extractDomain(websiteDomain || publicLink);
        var formattedDate = formatSheetDate(dated);

        // Specific sheet_row override
        if (payload.sheet_row) {
          var explicitRow = parseInt(payload.sheet_row, 10);
          if (explicitRow > dataStartRow && explicitRow <= allData.length) {
            sheet.getRange(explicitRow, COL_PUBLIC + 1).setValue(publicLink);
            sheet.getRange(explicitRow, COL_DATED  + 1).setValue(formattedDate);
            SpreadsheetApp.flush();
            var syncResult = {
              success: true,
              sheet: sheetName,
              row: explicitRow,
              message: 'Blog successfully synchronized.'
            };
            if (requestId) storeIdempotencyResult(requestId, syncResult);
            lock.releaseLock();
            return buildResponse(syncResult);
          }
        }

        var websiteRows = [];

        for (var r = dataStartRow; r < allData.length; r++) {
          var cellClass     = String(allData[r][COL_CLASS]   || '').trim();
          var cellDoc       = String(allData[r][COL_DOC]     || '').trim();
          var cellWebsite   = String(allData[r][COL_WEBSITE] || '').trim();
          var cellWebDomain = extractDomain(cellWebsite);

          // Domain matching
          var webMatches = (cellWebsite.toLowerCase() === websiteDomain.toLowerCase()) ||
                           (cellWebDomain !== '' && cellWebDomain === targetWeb) ||
                           (cellWebsite.indexOf(targetWeb) !== -1);

          // Class matching
          var classMatches = (cellClass === classNum || 
                              cellClass === 'Class ' + classNum || 
                              block.declaredClass === classNum ||
                              (classNum === '4' && (cellClass === '3' || cellClass === '4')) ||
                              (classNum === '3' && (cellClass === '3' || cellClass === '4')));

          if (webMatches && classMatches) {
            var cellPublic   = String(allData[r][COL_PUBLIC] || '').trim();
            var cellDatedVal = allData[r][COL_DATED];
            var hasDoc       = (cellDoc !== '' && cellDoc !== '-' && cellDoc.toLowerCase() !== 'n/a');
            
            // A cell ONLY has a Public Link if it contains an actual URL (http:// or https://)
            var isEmptyPublic = (cellPublic === '' || cellPublic === '-' || cellPublic.toLowerCase() === 'n/a' || !/^https?:\/\//i.test(cellPublic));

            websiteRows.push({
              rowIndex: r,
              sheetRow: r + 1,
              docLink: cellDoc,
              hasDoc: hasDoc,
              publicLink: isEmptyPublic ? '' : cellPublic,
              dated: cellDatedVal,
              isDateMatch: datesMatch(cellDatedVal, dated),
              isUrlMatch: (!isEmptyPublic) && normaliseUrl(cellPublic) === normLink,
              isEmptyPublic: isEmptyPublic
            });
          }
        }

        if (websiteRows.length === 0) {
          lock.releaseLock();
          return buildResponse({
            success: false,
            message: 'Website "' + websiteDomain + '" was not found in Class ' + classNum + ' (tab "' + sheetName + '"). Please make sure this website exists in Class ' + classNum + '.'
          });
        }

        var target = null;

        // 1. Check rows matching the requested date
        var dateMatchingRows = websiteRows.filter(function(w) { return w.isDateMatch; });

        if (dateMatchingRows.length > 0) {
          var dateRowsWithDoc = dateMatchingRows.filter(function(w) { return w.hasDoc; });

          if (dateRowsWithDoc.length === 0) {
            // Row exists for this date, but does NOT have a Doc Link yet!
            lock.releaseLock();
            return buildResponse({
              success: false,
              message: 'This row does not have a Doc Link yet. The scheduled row for "' + websiteDomain + '" on ' + dated + ' in Class ' + classNum + ' (tab "' + sheetName + '") is missing a Doc Link (\'Link\'). Please add the Doc Link in Google Sheet before importing.'
            });
          }

          // Priority 1: URL match (idempotent re-sync)
          var urlMatchOnDate = dateRowsWithDoc.filter(function(w) { return w.isUrlMatch; });
          if (urlMatchOnDate.length > 0) {
            target = urlMatchOnDate[0];
          } else {
            // Priority 2: Empty Public Link on this date
            var emptyOnDate = dateRowsWithDoc.filter(function(w) { return w.isEmptyPublic; });
            if (emptyOnDate.length > 0) {
              target = emptyOnDate[0];
            } else if (payload.force_overwrite) {
              target = dateRowsWithDoc[0];
            } else {
              lock.releaseLock();
              return buildResponse({
                success: false,
                needs_confirmation: true,
                sheet_row: dateRowsWithDoc[0].sheetRow,
                existing_public_link: dateRowsWithDoc[0].publicLink,
                message: 'Row ' + dateRowsWithDoc[0].sheetRow + ' for "' + websiteDomain + '" on ' + dated + ' in Class ' + classNum + ' (tab "' + sheetName + '") already has a Public Link (' + dateRowsWithDoc[0].publicLink + '). Confirm overwrite to replace it.'
              });
            }
          }
        } else {
          // 2. No exact date match found in the sheet:
          var rowsWithDoc = websiteRows.filter(function(w) { return w.hasDoc; });

          if (rowsWithDoc.length === 0) {
            lock.releaseLock();
            return buildResponse({
              success: false,
              message: 'This row does not have a Doc Link yet. None of the rows for "' + websiteDomain + '" in Class ' + classNum + ' (tab "' + sheetName + '") have a Doc Link (\'Link\') yet. Please add the Doc Link in Google Sheet before importing.'
            });
          }

          // Priority 1: URL match across rows
          var urlMatches = rowsWithDoc.filter(function(w) { return w.isUrlMatch; });
          if (urlMatches.length > 0) {
            target = urlMatches[0];
          } else {
            // Priority 2: First empty slot with Doc Link
            var emptySlots = rowsWithDoc.filter(function(w) { return w.isEmptyPublic; });
            if (emptySlots.length > 0) {
              target = emptySlots[0];
            } else if (payload.force_overwrite) {
              target = rowsWithDoc[0];
            } else {
              lock.releaseLock();
              return buildResponse({
                success: false,
                needs_confirmation: true,
                sheet_row: rowsWithDoc[0].sheetRow,
                existing_public_link: rowsWithDoc[0].publicLink,
                message: 'All scheduled rows with a Doc Link for "' + websiteDomain + '" in Class ' + classNum + ' (tab "' + sheetName + '") already have a Public Link (' + rowsWithDoc[0].publicLink + '). Confirm overwrite to replace it.'
              });
            }
          }
        }

        if (!target) {
          lock.releaseLock();
          return buildResponse({
            success: false,
            message: 'Could not determine target row for "' + websiteDomain + '" in Class ' + classNum + ' (tab "' + sheetName + '"). Please check the Google Sheet.'
          });
        }

        var writeSheetRow = target.sheetRow;

        // Update ONLY Public Link and Dated; keep Doc Link, Website, Writer intact!
        sheet.getRange(writeSheetRow, COL_PUBLIC + 1).setValue(publicLink);
        sheet.getRange(writeSheetRow, COL_DATED  + 1).setValue(formattedDate);
        SpreadsheetApp.flush();

        var syncResult = {
          success: true,
          sheet: sheetName,
          row: writeSheetRow,
          message: 'Blog successfully synchronized into row ' + writeSheetRow + ' with existing Doc Link.'
        };
        if (requestId) storeIdempotencyResult(requestId, syncResult);
        lock.releaseLock();
        return buildResponse(syncResult);
      }

      // ── Standard Blog Push (Fallback) ─────────────────────────────────────
      var exactMatchEmptyIndex = -1;
      var genericEmptyIndex = -1;

      for (var r = dataStartRow; r < allData.length; r++) {
        var cellClass   = String(allData[r][COL_CLASS]   || '').trim();
        var cellPublic  = String(allData[r][COL_PUBLIC]  || '').trim();
        var cellDated   = String(allData[r][COL_DATED]   || '').trim();
        var cellWebsite = String(allData[r][COL_WEBSITE] || '').trim();

        if (cellPublic === '' || !/^https?:\/\//i.test(cellPublic)) {
          if ((cellClass === classNum || cellClass === '') && cellDated === dated && (cellWebsite === websiteDomain || cellWebsite === '')) {
            if (exactMatchEmptyIndex === -1) exactMatchEmptyIndex = r;
          } else if (genericEmptyIndex === -1 && cellWebsite === '') {
            genericEmptyIndex = r;
          }
          continue;
        }

        if (normaliseUrl(cellPublic) === normLink) {
          var dupResult = {
            success:   false,
            duplicate: true,
            message:   'This blog link already exists in Class ' + classNum + ' (row ' + (r + 1) + ').',
            class:     classNum,
            row:       r + 1
          };
          if (requestId) storeIdempotencyResult(requestId, dupResult);
          return buildResponse(dupResult);
        }
      }

      var writeRowIndex = exactMatchEmptyIndex !== -1 ? exactMatchEmptyIndex : (genericEmptyIndex !== -1 ? genericEmptyIndex : allData.length);
      var writeSheetRow = writeRowIndex + 1;

      if (writeRowIndex >= allData.length && writeRowIndex > 0) {
        var sourceRange = sheet.getRange(writeRowIndex, 1, 1, sheet.getMaxColumns());
        var targetRange = sheet.getRange(writeRowIndex + 1, 1, 1, sheet.getMaxColumns());
        sourceRange.copyTo(targetRange, SpreadsheetApp.CopyPasteType.PASTE_FORMAT, false);
      }

      sheet.getRange(writeSheetRow, COL_CLASS   + 1).setValue(classNum);
      sheet.getRange(writeSheetRow, COL_PUBLIC  + 1).setValue(publicLink);
      sheet.getRange(writeSheetRow, COL_DATED   + 1).setValue(formatSheetDate(dated));
      sheet.getRange(writeSheetRow, COL_WEBSITE + 1).setValue(websiteDomain);
      SpreadsheetApp.flush();

      var successResult = {
        success: true,
        message: 'Blog added successfully to Class ' + classNum + ' in row ' + writeSheetRow + '.',
        class:   classNum,
        row:     writeSheetRow
      };

      if (requestId) storeIdempotencyResult(requestId, successResult);
      return buildResponse(successResult);

    } finally {
      lock.releaseLock();
    }

  } catch (err) {
    Logger.log('Unhandled error in doPost: ' + err.message + '\n' + err.stack);
    return buildResponse({
      success: false,
      message: 'Internal server error: ' + err.message
    });
  }
}

// ── ❸  DYNAMIC COLUMN & BLOCK DISCOVERY ──────────────────────────────────────

/**
 * Dynamically identifies the class block and column positions.
 * Tested against the live structure of "Sep Blogs", "Oct Blogs", "Blogs":
 * Row 1: "Class 3th & 4th", "Class 2nd", "Class 1st", "Class 5th", "Class 6th", "Class 7th"
 * Row 2: "Class", "Doc Link", "Public Link", "Dated", "Website link", "Writer"
 */
function findClassBlock(allData, classNum, targetWebsiteDomain) {
  if (!allData || allData.length === 0) return null;

  var numRows = allData.length;
  var numCols = allData[0].length;
  var row0 = allData[0];
  var row1 = numRows > 1 ? allData[1] : null;

  // Standard 7-column offsets per class:
  var standardOffsets = {
    '3': 0,
    '4': 0,
    '2': 7,
    '1': 14,
    '5': 21,
    '6': 28,
    '7': 35
  };

  // Header row index: row 2 (0-indexed 1) in standard sheet
  var headerRowIndex = 1;
  var dataStartRow = 2; // Data rows start at row 3 (0-indexed 2)

  // Verify whether row 2 has subheaders
  if (row1) {
    var hasSubHeaders = false;
    for (var c = 0; c < numCols; c++) {
      var h = String(row1[c] || '').toLowerCase();
      if (h.indexOf('link') !== -1 || h.indexOf('doc') !== -1 || h.indexOf('public') !== -1 || 
          h.indexOf('date') !== -1 || h.indexOf('writer') !== -1 || h === 'class' || /^class\b/i.test(h)) {
        hasSubHeaders = true;
        break;
      }
    }
    if (!hasSubHeaders) {
      headerRowIndex = 0;
      dataStartRow = 1;
    }
  } else {
    headerRowIndex = 0;
    dataStartRow = 1;
  }

  var groupRow = (headerRowIndex === 1) ? row0 : null;
  var subRow   = allData[headerRowIndex];

  // 1. Locate all Class start columns
  var classColIndices = [];
  for (var c = 0; c < numCols; c++) {
    var hSub = String(subRow[c] || '').trim().toLowerCase();
    var hGrp = groupRow ? String(groupRow[c] || '').trim().toLowerCase() : '';

    if (hSub === 'class' || (headerRowIndex === 1 && /^class\b/i.test(hGrp) && (c % 7 === 0))) {
      if (classColIndices.indexOf(c) === -1) {
        classColIndices.push(c);
      }
    }
  }

  if (classColIndices.length === 0) {
    classColIndices = [0, 7, 14, 21, 28, 35];
  }

  // 2. Build block configurations
  var blocks = [];
  for (var i = 0; i < classColIndices.length; i++) {
    var startCol = classColIndices[i];
    var nextCol = (i + 1 < classColIndices.length) ? classColIndices[i + 1] : Math.min(startCol + 7, numCols);

    var block = {
      colClass:   startCol + 0,
      colDoc:     startCol + 1,
      colPublic:  startCol + 2,
      colDated:   startCol + 3,
      colWebsite: startCol + 4,
      colWriter:  (startCol + 5 < numCols) ? startCol + 5 : -1,
      declaredClass: null,
      dataStartRow: dataStartRow
    };

    // Check group header e.g. "Class 3th & 4th", "Class 2nd"
    if (groupRow) {
      for (var gc = startCol; gc < Math.min(startCol + 4, numCols); gc++) {
        var gText = String(groupRow[gc] || '').trim();
        var m = gText.match(/class\s*([0-9]+)/i);
        if (m) {
          block.declaredClass = m[1];
          break;
        }
      }
    }

    // Refine with sub-headers if available
    var genericLinks = [];
    for (var col = startCol + 1; col < nextCol; col++) {
      var h = String(subRow[col] || '').trim().toLowerCase();
      if (!h) continue;

      if (h.indexOf('doc') !== -1 || h === 'draft link' || h === 'article link') {
        block.colDoc = col;
      } else if (h.indexOf('public') !== -1 || h.indexOf('live') !== -1) {
        block.colPublic = col;
      } else if (h === 'link' || h === 'links') {
        genericLinks.push(col);
      } else if (h.indexOf('date') !== -1) {
        block.colDated = col;
      } else if (h.indexOf('web') !== -1 || h.indexOf('site') !== -1 || h.indexOf('domain') !== -1) {
        block.colWebsite = col;
      } else if (h.indexOf('writer') !== -1 || h.indexOf('author') !== -1) {
        block.colWriter = col;
      }
    }

    // If both columns are generic "Link": first is Doc Link, second is Public Link!
    if (genericLinks.length >= 2) {
      block.colDoc = genericLinks[0];
      block.colPublic = genericLinks[1];
    } else if (genericLinks.length === 1) {
      if (block.colDoc === block.colPublic) {
        block.colPublic = block.colDoc + 1;
      }
    }

    // Strict safety invariant: colPublic must NEVER equal colClass or colDoc!
    if (block.colPublic === block.colClass) {
      block.colPublic = block.colClass + 2;
    }
    if (block.colDoc === block.colClass) {
      block.colDoc = block.colClass + 1;
    }
    if (block.colPublic === block.colDoc) {
      block.colPublic = block.colDoc + 1;
    }

    blocks.push(block);
  }

  // 3. Match block by Class & Website Domain
  var normTargetWeb = targetWebsiteDomain ? extractDomain(targetWebsiteDomain) : '';
  var bestBlock = null;
  var bestScore = -1;

  for (var b = 0; b < blocks.length; b++) {
    var blk = blocks[b];
    var score = 0;

    // Direct header declaration match
    if (blk.declaredClass) {
      if (blk.declaredClass === classNum) score += 60;
      if (classNum === '4' && blk.declaredClass === '3') score += 50;
      if (classNum === '3' && blk.declaredClass === '4') score += 50;
    }

    // Inspect data rows under this block
    var classMatchCount = 0;
    var webMatchCount = 0;

    for (var r = blk.dataStartRow; r < numRows; r++) {
      var row = allData[r];
      var rClass = String(row[blk.colClass] || '').trim();
      var rWeb = String(row[blk.colWebsite] || '').trim();
      var rDoc = String(row[blk.colDoc] || '').trim();

      if (rClass === classNum || (classNum === '4' && (rClass === '3' || rClass === '4')) || (classNum === '3' && (rClass === '3' || rClass === '4'))) {
        classMatchCount++;
      }
      if (normTargetWeb && extractDomain(rWeb) === normTargetWeb) {
        webMatchCount++;
        if (rDoc !== '') score += 40;
      }
    }

    if (classMatchCount > 0) score += 30;
    if (webMatchCount > 0) score += 50;

    if (score > bestScore) {
      bestScore = score;
      bestBlock = blk;
    }
  }

  if (bestScore > 0 && bestBlock) {
    return bestBlock;
  }

  // Fallback to standard offset map
  if (standardOffsets.hasOwnProperty(classNum)) {
    var targetStartCol = standardOffsets[classNum];
    for (var b = 0; b < blocks.length; b++) {
      if (blocks[b].colClass === targetStartCol) {
        return blocks[b];
      }
    }
    return {
      colClass: targetStartCol,
      colDoc: targetStartCol + 1,
      colPublic: targetStartCol + 2,
      colDated: targetStartCol + 3,
      colWebsite: targetStartCol + 4,
      colWriter: (targetStartCol + 5 < numCols) ? targetStartCol + 5 : -1,
      declaredClass: classNum,
      dataStartRow: dataStartRow
    };
  }

  return blocks[0] || null;
}

// ── ❹  GET HANDLER ───────────────────────────────────────────────────────────

function doGet(e) {
  return buildResponse({
    ok:        true,
    service:   'DGT Blogs Sheet Integration',
    version:   '3.2.0',
    timestamp: new Date().toISOString()
  });
}

// ── ❺  HELPERS ───────────────────────────────────────────────────────────────

function validateFields(classNum, publicLink, dated) {
  if (!classNum)   return 'Missing required field: class.';
  if (!publicLink) return 'Missing required field: public_link.';
  if (!dated)      return 'Missing required field: date.';

  if (!/^https?:\/\/.+/i.test(publicLink)) {
    return 'public_link must start with http:// or https://.';
  }
  return null;
}

function extractDomain(url) {
  try {
    var match = String(url || '').match(/^https?:\/\/([^\/\?#]+)/i);
    if (!match) {
      var plain = String(url || '').split('/')[0].trim().toLowerCase();
      return plain.replace(/^www\./, '').replace(/:\d+$/, '');
    }
    var host = match[1].toLowerCase();
    host = host.replace(/^www\./, '');
    host = host.replace(/:\d+$/, '');
    return host;
  } catch (err) {
    return '';
  }
}

function normaliseUrl(url) {
  return String(url || '').trim().toLowerCase().replace(/#+[^#]*$/, '').replace(/\/+$/, '');
}

function formatSheetDate(dateStr) {
  if (!dateStr) return '';
  var s = String(dateStr).trim();
  if (/^\d{2}\/\d{2}$/.test(s)) return s;
  var d = new Date(s);
  if (!isNaN(d.getTime())) {
    var mm = ('0' + (d.getMonth() + 1)).slice(-2);
    var dd = ('0' + d.getDate()).slice(-2);
    return mm + '/' + dd;
  }
  var m = s.match(/(\d{1,2})[\/\-](\d{1,2})/);
  if (m) {
    var p1 = ('0' + m[1]).slice(-2);
    var p2 = ('0' + m[2]).slice(-2);
    return p1 + '/' + p2;
  }
  return s;
}

function datesMatch(cellDateVal, targetDateStr) {
  if (!cellDateVal || !targetDateStr) return false;

  var s1 = String(cellDateVal).trim();
  var s2 = String(targetDateStr).trim();
  if (s1 === s2) return true;

  var d1 = null, d2 = null;
  if (cellDateVal instanceof Date) {
    d1 = cellDateVal;
  } else {
    var parsed1 = new Date(cellDateVal);
    if (!isNaN(parsed1.getTime())) d1 = parsed1;
  }

  var parsed2 = new Date(targetDateStr);
  if (!isNaN(parsed2.getTime())) d2 = parsed2;

  if (d1 && d2) {
    return d1.getMonth() === d2.getMonth() && d1.getDate() === d2.getDate();
  }

  // Regex match for MM/DD or DD/MM
  var m1 = s1.match(/(\d{1,2})[\/\-](\d{1,2})/);
  var m2 = s2.match(/(\d{1,2})[\/\-](\d{1,2})/);
  if (m1 && m2) {
    var v1a = parseInt(m1[1], 10), v1b = parseInt(m1[2], 10);
    var v2a = parseInt(m2[1], 10), v2b = parseInt(m2[2], 10);
    return (v1a === v2a && v1b === v2b) || (v1a === v2b && v1b === v2a);
  }

  if (d1 && m2) {
    var mMonth = d1.getMonth() + 1;
    var mDay = d1.getDate();
    var p1 = parseInt(m2[1], 10), p2 = parseInt(m2[2], 10);
    return (mMonth === p1 && mDay === p2) || (mMonth === p2 && mDay === p1);
  }

  return false;
}

function constantTimeEqual(a, b) {
  if (a.length !== b.length) return false;
  var result = 0;
  for (var i = 0; i < a.length; i++) {
    result |= a.charCodeAt(i) ^ b.charCodeAt(i);
  }
  return result === 0;
}

function buildResponse(data) {
  return ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}

// ── ❻  IDEMPOTENCY ───────────────────────────────────────────────────────────

var IDEM_PREFIX = 'idem_';

function getIdempotencyResult(requestId) {
  try {
    var props = PropertiesService.getScriptProperties();
    var stored = props.getProperty(IDEM_PREFIX + requestId);
    if (!stored) return null;
    return JSON.parse(stored);
  } catch (err) {
    Logger.log('Idempotency read error: ' + err.message);
    return null;
  }
}

function storeIdempotencyResult(requestId, result) {
  try {
    var props  = PropertiesService.getScriptProperties();
    var key    = IDEM_PREFIX + requestId;
    var allKeys = props.getKeys().filter(function(k) { return k.indexOf(IDEM_PREFIX) === 0; });

    if (allKeys.length >= IDEMPOTENCY_CACHE_SIZE) {
      var toDelete = allKeys.slice(0, Math.floor(IDEMPOTENCY_CACHE_SIZE / 4));
      toDelete.forEach(function(k) { props.deleteProperty(k); });
    }
    props.setProperty(key, JSON.stringify(result));
  } catch (err) {
    Logger.log('Idempotency store error: ' + err.message);
  }
}
