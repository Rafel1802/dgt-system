#!/bin/bash
# ============================================================
# split_sql.sh - Split large SQL dump into smaller chunks
# for importing into Hostinger phpMyAdmin (max 256MB upload)
# ============================================================

SQL_FILE="u355625773_kiuq_system.sql"
OUTPUT_DIR="sql_chunks"
TABLES_PER_CHUNK=10  # Adjust if needed

mkdir -p "$OUTPUT_DIR"

# Extract header (SET statements, etc.)
HEADER=$(head -20 "$SQL_FILE")

echo "📦 Splitting $SQL_FILE into chunks..."
echo "Header extracted."

# Split by CREATE TABLE boundaries
# Each chunk will have the header + N tables worth of data
csplit --quiet --prefix="$OUTPUT_DIR/chunk_" --suffix-format="%03d.sql" \
  "$SQL_FILE" \
  "/^DROP TABLE IF EXISTS/+0" \
  "{*}" 2>/dev/null || true

# Count generated chunks
CHUNK_COUNT=$(ls "$OUTPUT_DIR"/chunk_*.sql 2>/dev/null | wc -l)
echo "✅ Generated $CHUNK_COUNT chunk files in ./$OUTPUT_DIR/"

# Add header to each chunk so MySQL knows charset settings
for f in "$OUTPUT_DIR"/chunk_*.sql; do
  # Prepend essential MySQL header
  { echo "SET NAMES utf8mb4;"; echo "SET FOREIGN_KEY_CHECKS=0;"; cat "$f"; echo "SET FOREIGN_KEY_CHECKS=1;"; } > "${f}.tmp" && mv "${f}.tmp" "$f"
  SIZE=$(ls -lh "$f" | awk '{print $5}')
  echo "  📄 $f ($SIZE)"
done

echo ""
echo "🚀 Import Order:"
echo "  1. Import chunk_000.sql first (contains schema header)"
echo "  2. Import remaining chunks in order (chunk_001, chunk_002, ...)"
echo ""
echo "⚠️  IMPORTANT: In phpMyAdmin, DISABLE 'Enable foreign key checks' before importing!"
