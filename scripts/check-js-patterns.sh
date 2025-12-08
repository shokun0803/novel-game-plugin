#!/bin/bash
#
# JavaScript パターンチェックスクリプト
# CI およびローカルで JavaScript ファイルの禁止パターンをチェックします
#
# Usage: ./scripts/check-js-patterns.sh
# Exit: 0 = OK, 1 = 禁止パターン検出
#

set -euo pipefail

echo "Checking JavaScript files for forbidden patterns..."
echo ""

FORBIDDEN_FOUND=0

# ========================================
# 1. Check for forbidden console.* usage
# ========================================
echo "1. Checking for forbidden console.* usage..."
echo "   検出パターン: console\\.(log|warn|error|info|debug)\\b"

for file in $(find js -name "*.js" -type f ! -name "debug-log.js"); do
  while IFS= read -r match; do
    line_num=$(echo "$match" | cut -d: -f1)
    line_content=$(echo "$match" | cut -d: -f2-)

    # Skip typeof console checks
    if echo "$line_content" | grep -qE 'typeof console'; then
      continue
    fi

    # Skip documentation comments
    if echo "$line_content" | grep -qE '^\s*\*'; then
      continue
    fi

    # Skip single-line comments
    if echo "$line_content" | grep -qE '^\s*//'; then
      continue
    fi
    
    # Skip block comments
    if echo "$line_content" | grep -qE '/\*.*console\.' && echo "$line_content" | grep -q '\*/'; then
      continue
    fi

    # Check context for novelGameShowFlags and novelGameSetDebug
    start_line=$((line_num - 20))
    if [ $start_line -lt 1 ]; then
      start_line=1
    fi
    context=$(sed -n "${start_line},${line_num}p" "$file")
    
    if echo "$context" | grep -qE 'novelGameShowFlags|novelGameSetDebug'; then
      continue
    fi

    echo "❌ Forbidden console usage found in $file:$line_num"
    echo "   $line_content"
    
    context_start=$((line_num - 2))
    context_end=$((line_num + 2))
    if [ $context_start -lt 1 ]; then
      context_start=1
    fi
    echo "   Context:"
    sed -n "${context_start},${context_end}p" "$file" | while IFS= read -r ctx_line; do
      echo "     $ctx_line"
    done
    echo ""
    
    FORBIDDEN_FOUND=1

  done < <(grep -nP 'console\.(log|warn|error|info|debug)\b' "$file" 2>/dev/null || true)
done

# ========================================
# 2. Check for eval() usage
# ========================================
echo ""
echo "2. Checking for eval() usage..."
echo "   検出パターン: \\beval\\s*\\("

for file in $(find js -name "*.js" -type f); do
  while IFS= read -r match; do
    line_num=$(echo "$match" | cut -d: -f1)
    line_content=$(echo "$match" | cut -d: -f2-)

    if echo "$line_content" | grep -qE '^\s*(//|\*)'; then
      continue
    fi
    
    if echo "$line_content" | grep -qE '/\*.*eval.*\*/'; then
      continue
    fi

    echo "❌ Dangerous eval() found in $file:$line_num"
    echo "   $line_content"
    FORBIDDEN_FOUND=1

  done < <(grep -nP '\beval\s*\(' "$file" 2>/dev/null || true)
done

# ========================================
# 3. Check for new Function() usage
# ========================================
echo ""
echo "3. Checking for new Function() usage..."
echo "   検出パターン: \\bnew\\s+Function\\s*\\("

for file in $(find js -name "*.js" -type f); do
  while IFS= read -r match; do
    line_num=$(echo "$match" | cut -d: -f1)
    line_content=$(echo "$match" | cut -d: -f2-)

    if echo "$line_content" | grep -qE '^\s*(//|\*)'; then
      continue
    fi
    
    if echo "$line_content" | grep -qE '/\*.*new\s+Function.*\*/'; then
      continue
    fi

    echo "❌ Dangerous new Function() found in $file:$line_num"
    echo "   $line_content"
    FORBIDDEN_FOUND=1

  done < <(grep -nP '\bnew\s+Function\s*\(' "$file" 2>/dev/null || true)
done

# ========================================
# 4. Check for setTimeout/setInterval with string evaluation
# ========================================
echo ""
echo "4. Checking for setTimeout/setInterval with string evaluation..."
echo "   検出パターン: set(Timeout|Interval)\\s*\\(\\s*[\"'\`]"

for file in $(find js -name "*.js" -type f); do
  while IFS= read -r match; do
    line_num=$(echo "$match" | cut -d: -f1)
    line_content=$(echo "$match" | cut -d: -f2-)

    if echo "$line_content" | grep -qE '^\s*(//|\*)'; then
      continue
    fi

    echo "❌ Dangerous setTimeout/setInterval with string found in $file:$line_num"
    echo "   $line_content"
    FORBIDDEN_FOUND=1

  done < <(grep -nP 'set(Timeout|Interval)\s*\(\s*["'"'"'`]' "$file" 2>/dev/null || true)
done

# ========================================
# 5. Check for innerHTML usage (WARNING ONLY)
# ========================================
echo ""
echo "5. Checking for innerHTML usage (WARNING ONLY)..."
echo "   検出パターン: \\.innerHTML\\s*(\\+?=)"

for file in $(find js -name "*.js" -type f); do
  while IFS= read -r match; do
    line_num=$(echo "$match" | cut -d: -f1)
    line_content=$(echo "$match" | cut -d: -f2-)

    if echo "$line_content" | grep -qE '^\s*(//|\*)'; then
      continue
    fi

    echo "⚠️  innerHTML usage found in $file:$line_num (verify proper escaping)"
    echo "   $line_content"

  done < <(grep -nP '\.innerHTML\s*(\+?=)' "$file" 2>/dev/null || true)
done

# ========================================
# 6. Check for insertAdjacentHTML usage (WARNING ONLY)
# ========================================
echo ""
echo "6. Checking for insertAdjacentHTML usage (WARNING ONLY)..."
echo "   検出パターン: insertAdjacentHTML\\s*\\("

for file in $(find js -name "*.js" -type f); do
  while IFS= read -r match; do
    line_num=$(echo "$match" | cut -d: -f1)
    line_content=$(echo "$match" | cut -d: -f2-)

    if echo "$line_content" | grep -qE '^\s*(//|\*)'; then
      continue
    fi

    echo "⚠️  insertAdjacentHTML usage found in $file:$line_num (verify proper escaping)"
    echo "   $line_content"

  done < <(grep -nP 'insertAdjacentHTML\s*\(' "$file" 2>/dev/null || true)
done

# ========================================
# Results
# ========================================
echo ""
if [ $FORBIDDEN_FOUND -eq 1 ]; then
  echo "=========================================="
  echo "ERROR: Forbidden patterns detected!"
  echo ""
  echo "Guidelines:"
  echo "  - Use debugLog() instead of console.*"
  echo "  - Avoid eval() and new Function() - use safer alternatives"
  echo "  - Don't use setTimeout/setInterval with string evaluation"
  echo "  - Prefer textContent over innerHTML for XSS safety"
  echo ""
  echo "See docs/DEVELOPER_LOGGING_GUIDELINES.md for details."
  echo "=========================================="
  exit 1
else
  echo "✅ No forbidden patterns found."
fi
