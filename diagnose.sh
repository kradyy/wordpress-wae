#!/bin/bash

echo "=== MCP WordPress Capabilities Diagnostic ==="
echo ""

# Check WordPress accessibility
echo "1. Checking WordPress..."
HTTP_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" https://127.0.0.1:9443/)
if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "301" ] || [ "$HTTP_CODE" = "302" ]; then
    echo "   ✓ WordPress is accessible (HTTP $HTTP_CODE)"
else
    echo "   ✗ WordPress is not accessible (HTTP $HTTP_CODE)"
    exit 1
fi

# Check REST API
echo ""
echo "2. Checking REST API..."
REST_CHECK=$(curl -k -s https://127.0.0.1:9443/wp-json/ | grep -o '"namespaces"' | head -1)
if [ -n "$REST_CHECK" ]; then
    echo "   ✓ REST API is working"
else
    echo "   ✗ REST API is not working"
    exit 1
fi

# Check for MCP namespace
echo ""
echo "3. Checking for MCP namespace..."
MCP_CHECK=$(curl -k -s https://127.0.0.1:9443/wp-json/ | grep -o '"mcp"' | head -1)
if [ -n "$MCP_CHECK" ]; then
    echo "   ✓ MCP namespace found"
    echo ""
    echo "   Available MCP routes:"
    curl -k -s https://127.0.0.1:9443/wp-json/ | grep -o '"mcp[^"]*"' | sed 's/"//g' | sed 's/^/     - /'
else
    echo "   ✗ MCP namespace NOT found"
    echo ""
    echo "   This means the MCP Adapter plugin is either:"
    echo "     - Not installed"
    echo "     - Not activated"
    echo "     - Not properly configured"
    echo ""
    echo "   To fix this:"
    echo "   1. Download mcp-adapter from: https://github.com/WordPress/mcp-adapter/releases"
    echo "   2. Install and activate it in WordPress admin"
    echo "   3. Ensure this plugin (mcp-wp-capabilities) is also activated"
fi

# Check MCP endpoint
echo ""
echo "4. Testing MCP endpoint..."
RESPONSE=$(curl -k -s -w "\n%{http_code}" -X POST \
  -u "chris:XmSn lp8w IhJa Laco SX6E vaph" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"initialize","id":1,"params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}' \
  https://127.0.0.1:9443/wp-json/mcp/mcp-adapter-default-server 2>&1)

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" = "200" ]; then
    echo "   ✓ MCP endpoint is responding (HTTP 200)"
    
    # Try to get session ID
    SESSION=$(echo "$BODY" | grep -o '"mcp-session-id":"[^"]*"' | cut -d'"' -f4)
    if [ -n "$SESSION" ]; then
        echo "   ✓ Got session ID: $SESSION"
        
        # Try to discover abilities
        echo ""
        echo "5. Discovering abilities..."
        ABILITIES=$(curl -k -s -X POST \
          -u "chris:XmSn lp8w IhJa Laco SX6E vaph" \
          -H "Content-Type: application/json" \
          -H "Mcp-Session-Id: $SESSION" \
          -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"mcp-adapter-discover-abilities","arguments":{}}}' \
          https://127.0.0.1:9443/wp-json/mcp/mcp-adapter-default-server)
        
        COUNT=$(echo "$ABILITIES" | grep -o '"mcp-wp/' | wc -l | tr -d ' ')
        if [ "$COUNT" -gt "0" ]; then
            echo "   ✓ Found $COUNT mcp-wp abilities"
            echo ""
            echo "   Registered abilities:"
            echo "$ABILITIES" | grep -o '"mcp-wp/[^"]*"' | sed 's/"//g' | sed 's/^/     - /'
        else
            echo "   ✗ No mcp-wp abilities found"
            echo ""
            echo "   This means:"
            echo "     - The plugin is not registering abilities correctly"
            echo "     - The MCP server is not exposing them"
            echo "     - Check WordPress debug.log for errors"
        fi
    fi
elif [ "$HTTP_CODE" = "404" ]; then
    echo "   ✗ MCP endpoint not found (HTTP 404)"
    echo "   The MCP Adapter plugin is not creating the endpoint"
else
    echo "   ✗ MCP endpoint error (HTTP $HTTP_CODE)"
    echo "   Response: $BODY"
fi

echo ""
echo "=== Diagnostic Complete ==="
