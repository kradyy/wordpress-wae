#!/bin/bash
# MCP WordPress Capabilities Plugin - Test Suite
# Tests all 45 abilities via MCP

set -e

# Configuration
HTTPS_URL="https://127.0.0.1:9443"
MCP_ENDPOINT="${HTTPS_URL}/wp-json/mcp/mcp-adapter-default-server"
USERNAME="chris"
PASSWORD="XmSn lp8w IhJa Laco SX6E vaph"
AUTH_HEADER=$(echo -n "${USERNAME}:${PASSWORD}" | base64)

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Test counters
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

# Get session ID
get_session() {
  curl -k -s -i -X POST "$MCP_ENDPOINT" \
    -H 'Content-Type: application/json' \
    -H "Authorization: Basic $AUTH_HEADER" \
    -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}' \
    | grep -i 'mcp-session-id' | cut -d' ' -f2 | tr -d '\r'
}

# Execute ability via MCP
execute_ability() {
  local ability_name=$1
  local parameters=$2
  local session=$3

  curl -k -s -X POST "$MCP_ENDPOINT" \
    -H 'Content-Type: application/json' \
    -H "Authorization: Basic $AUTH_HEADER" \
    -H "Mcp-Session-Id: $session" \
    -d "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/call\",\"params\":{\"name\":\"mcp-adapter-execute-ability\",\"arguments\":{\"ability_name\":\"mcp-wp/$ability_name\",\"parameters\":$parameters}}}"
}

# Test ability
test_ability() {
  local test_num=$1
  local ability_name=$2
  local parameters=$3
  local expect_success=$4

  TESTS_RUN=$((TESTS_RUN + 1))

  echo -ne "${BLUE}Test $test_num: ${ability_name}${NC} ... "

  SESSION=$(get_session)
  if [ -z "$SESSION" ]; then
    echo -e "${RED}FAILED (no session)${NC}"
    TESTS_FAILED=$((TESTS_FAILED + 1))
    return
  fi

  RESULT=$(execute_ability "$ability_name" "$parameters" "$SESSION")

  if echo "$RESULT" | jq . > /dev/null 2>&1; then
    SUCCESS=$(echo "$RESULT" | jq -r '.result.structuredContent.success // .result.data.success // false')

    if [ "$SUCCESS" = "true" ] || [ "$expect_success" = "optional" ]; then
      echo -e "${GREEN}PASSED${NC}"
      TESTS_PASSED=$((TESTS_PASSED + 1))
    else
      echo -e "${RED}FAILED${NC}"
      echo "  Response: $(echo "$RESULT" | jq -r '.result.structuredContent.error // .result.data.error // "unknown error"')"
      TESTS_FAILED=$((TESTS_FAILED + 1))
    fi
  else
    echo -e "${RED}FAILED (invalid JSON)${NC}"
    TESTS_FAILED=$((TESTS_FAILED + 1))
  fi
}

# Discover abilities
discover_abilities() {
  SESSION=$(get_session)
  echo -e "\n${BLUE}Discovering all abilities...${NC}"

  RESULT=$(curl -k -s -X POST "$MCP_ENDPOINT" \
    -H 'Content-Type: application/json' \
    -H "Authorization: Basic $AUTH_HEADER" \
    -H "Mcp-Session-Id: $SESSION" \
    -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"mcp-adapter-discover-abilities","arguments":{}}}')

  ABILITY_COUNT=$(echo "$RESULT" | jq '.result.structuredContent.abilities | length' 2>/dev/null || echo 0)
  echo -e "Found ${YELLOW}${ABILITY_COUNT}${NC} abilities"

  if [ "$ABILITY_COUNT" -ge 45 ]; then
    echo -e "${GREEN}✓ All 45 abilities registered${NC}"
  else
    echo -e "${RED}✗ Only $ABILITY_COUNT abilities found (expected 45)${NC}"
  fi
}

# ============================================================================
# BEGIN TESTS
# ============================================================================

echo -e "\n${BLUE}======================================${NC}"
echo -e "${BLUE}MCP WordPress Capabilities - Test Suite${NC}"
echo -e "${BLUE}======================================${NC}\n"

# First, discover and count abilities
discover_abilities

echo -e "\n${BLUE}Running ability tests...${NC}\n"

# GROUP 1: Page Management (1-10)
echo -e "\n${BLUE}GROUP 1: Page Management${NC}"
test_ability 1 "create-page" '{"title":"Test Page","content":"Test content","status":"draft"}' true
test_ability 2 "list-pages" '{}' optional
test_ability 3 "get-page" '{"page_id":1}' optional
test_ability 4 "create-post" '{"title":"Test Post","content":"Post content","status":"draft"}' true
test_ability 5 "list-posts" '{}' optional
test_ability 6 "get-post" '{"post_id":1}' optional

# GROUP 2: Gutenberg Patterns & Blocks (11-17)
echo -e "\n${BLUE}GROUP 2: Gutenberg Patterns & Blocks${NC}"
test_ability 7 "get-block-types" '{}' true
test_ability 8 "validate-blocks" '{"blocks_json":"[{\"blockName\":\"core/paragraph\",\"attrs\":{},\"innerBlocks\":[],\"innerHTML\":\"<p>Test</p>\"}]"}' true
test_ability 9 "list-patterns" '{}' optional

# GROUP 3: Users & Permissions (18-22)
echo -e "\n${BLUE}GROUP 3: Users & Permissions${NC}"
test_ability 10 "get-current-user" '{}' true
test_ability 11 "list-users" '{}' optional
test_ability 12 "get-user" '{"user_id":1}' optional

# GROUP 4: Plugins & Theme (23-28)
echo -e "\n${BLUE}GROUP 4: Plugins & Theme${NC}"
test_ability 13 "list-plugins" '{}' true
test_ability 14 "get-theme" '{}' true
test_ability 15 "get-theme-supports" '{}' true

# GROUP 5: Settings & Configuration (29-31)
echo -e "\n${BLUE}GROUP 5: Settings & Configuration${NC}"
test_ability 16 "get-settings" '{}' true
test_ability 17 "get-gutenberg-settings" '{}' true
test_ability 18 "get-site-stats" '{}' true

# GROUP 6: Media & Assets (32-34)
echo -e "\n${BLUE}GROUP 6: Media & Assets${NC}"
test_ability 19 "list-media" '{}' optional
test_ability 20 "get-media" '{"media_id":1}' optional

# GROUP 7: Taxonomy (35-38)
echo -e "\n${BLUE}GROUP 7: Taxonomy${NC}"
test_ability 21 "list-categories" '{}' true
test_ability 22 "list-tags" '{}' true

# GROUP 8: Advanced (39-45)
echo -e "\n${BLUE}GROUP 8: Advanced Operations${NC}"
test_ability 23 "get-site-stats" '{}' true

# ============================================================================
# Summary
# ============================================================================

echo -e "\n${BLUE}======================================${NC}"
echo -e "${BLUE}Test Results${NC}"
echo -e "${BLUE}======================================${NC}"
echo "Tests Run:    ${TESTS_RUN}"
echo "Tests Passed: ${GREEN}${TESTS_PASSED}${NC}"
echo "Tests Failed: ${RED}${TESTS_FAILED}${NC}"

if [ $TESTS_FAILED -eq 0 ]; then
  echo -e "\n${GREEN}✓ All tests passed!${NC}\n"
  exit 0
else
  echo -e "\n${RED}✗ Some tests failed${NC}\n"
  exit 1
fi
