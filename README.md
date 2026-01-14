# MCP WordPress Capabilities

Comprehensive WordPress capabilities plugin for Model Context Protocol (MCP) integration. Provides 45 carefully designed abilities enabling AI agents to interact with WordPress programmatically.

## Features

- **45 WordPress Abilities** organized in 8 logical groups
- **Full CRUD Operations** for pages, posts, patterns, users, media
- **Gutenberg Integration** with pattern management and block validation
- **Plugin & Theme Management** - list, activate, deactivate plugins and get theme info
- **User Management** with role-based permissions
- **Media Handling** with upload, list, and retrieval
- **Taxonomy Operations** for categories and tags
- **Advanced Features** including batch operations, pattern import/export, and item cloning
- **Proper Security** with capability checks and input validation
- **MCP Protocol Support** with public abilities exposed via MCP

## Abilities Overview

### Group 1: Page Management (1-10)
- `mcp-wp/create-page` - Create new pages
- `mcp-wp/edit-page` - Modify pages
- `mcp-wp/get-page` - Retrieve page details
- `mcp-wp/list-pages` - List pages with filtering
- `mcp-wp/delete-page` - Delete pages
- `mcp-wp/create-post` - Create posts
- `mcp-wp/edit-post` - Modify posts
- `mcp-wp/get-post` - Retrieve post details
- `mcp-wp/list-posts` - List posts with filtering
- `mcp-wp/delete-post` - Delete posts

### Group 2: Gutenberg Patterns & Blocks (11-17)
- `mcp-wp/list-patterns` - List all Gutenberg patterns
- `mcp-wp/get-pattern` - Get specific pattern
- `mcp-wp/create-pattern` - Create new pattern
- `mcp-wp/edit-pattern` - Modify pattern
- `mcp-wp/delete-pattern` - Delete pattern
- `mcp-wp/get-block-types` - List available blocks
- `mcp-wp/validate-blocks` - Validate block JSON

### Group 3: Users & Permissions (18-22)
- `mcp-wp/list-users` - List WordPress users
- `mcp-wp/get-user` - Get user details
- `mcp-wp/get-current-user` - Get authenticated user
- `mcp-wp/create-user` - Create new user
- `mcp-wp/edit-user` - Update user info

### Group 4: Plugins & Theme (23-28)
- `mcp-wp/list-plugins` - List installed plugins
- `mcp-wp/get-plugin` - Get plugin details
- `mcp-wp/activate-plugin` - Activate plugin
- `mcp-wp/deactivate-plugin` - Deactivate plugin
- `mcp-wp/get-theme` - Get theme information
- `mcp-wp/get-theme-supports` - Get theme features

### Group 5: Settings & Configuration (29-31)
- `mcp-wp/get-settings` - Get WordPress settings
- `mcp-wp/get-gutenberg-settings` - Get block editor config
- `mcp-wp/get-site-stats` - Get site statistics

### Group 6: Media & Assets (32-34)
- `mcp-wp/upload-media` - Upload media files
- `mcp-wp/list-media` - List media
- `mcp-wp/get-media` - Get media details

### Group 7: Taxonomy (35-38)
- `mcp-wp/list-categories` - List post categories
- `mcp-wp/list-tags` - List post tags
- `mcp-wp/create-category` - Create category
- `mcp-wp/create-tag` - Create tag

### Group 8: Advanced (39-45)
- `mcp-wp/custom-rest-call` - Make custom REST API calls
- `mcp-wp/query-posts-advanced` - Advanced post queries
- `mcp-wp/batch-update` - Update multiple items
- `mcp-wp/export-pattern` - Export pattern as JSON
- `mcp-wp/import-pattern` - Import pattern from JSON
- `mcp-wp/get-pattern-usage` - Find pattern usage
- `mcp-wp/clone-item` - Duplicate page/post

## Installation

### Prerequisites
1. WordPress 6.9+ (includes Abilities API)
2. MCP Adapter plugin (download from [GitHub releases](https://github.com/WordPress/mcp-adapter/releases))

### Steps
1. Install and activate the MCP Adapter plugin
2. Place this plugin folder in `wp-content/plugins/`
3. Activate through WordPress admin
4. Create an Application Password for API authentication (Users > Edit User > Application Passwords)

### MCP Client Configuration

Configure your MCP client (VS Code, Claude Desktop, etc.) with:

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://your-site.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-application-password"
      }
    }
  }
}
```

**Important**: Use HTTPS for the URL if your WordPress site uses SSL.

## Usage via MCP

All abilities are automatically exposed via MCP with:
- Full input/output schema definitions
- Proper permission checks
- Comprehensive error handling
- Structured response format

Example MCP call:
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "mcp-adapter-execute-ability",
    "arguments": {
      "ability_name": "mcp-wp/create-page",
      "parameters": {
        "title": "My Page",
        "content": "Page content",
        "status": "publish"
      }
    }
  }
}
```

## File Structure

```
mcp-wp-capabilities/
├── mcp-wp-capabilities.php      # Main plugin file
├── data/
│   ├── abilities.php            # Abilities 1-10 (Page/Post management)
│   ├── abilities-11-45.php      # Abilities 11-45 (Remaining groups)
│   └── class-ability-helpers.php # Helper class for common operations
├── README.md                    # This file
└── .git                         # Version control
```

## Architecture

### Plugin Entry Point (mcp-wp-capabilities.php)
- Registers ability category
- Hooks into WordPress Abilities API
- Loads ability definitions

### Ability Groups (data/abilities.php & data/abilities-11-45.php)
- 45 wp_register_ability() calls
- Organized in 8 logical groups
- Each ability includes input/output schemas
- Proper permission and validation callbacks

### Helper Class (data/class-ability-helpers.php)
- Common utilities for all abilities
- User capability checking
- Post/Page object retrieval
- Response formatting
- Block validation
- Pattern management helpers

## Security Considerations

- All inputs are sanitized (sanitize_text_field, wp_kses_post, etc.)
- User capabilities are checked for each operation
- Only authenticated users can use abilities
- Proper WordPress escaping applied
- Meta field keys are sanitized
- Taxonomy inputs validated

## Testing

The plugin includes diagnostic and test scripts:

### Quick Diagnostic
```bash
bash diagnose.sh
```
Checks WordPress connectivity, REST API, MCP namespace, and ability discovery.

### Full Test Suite
```bash
bash tests.sh
```
Tests all 45 abilities via MCP protocol.

### Manual Testing via MCP
1. Initialize session and get tools list
2. Call tools using the MCP protocol
3. Tool names use underscores: `mcp-wp-create-page` (not `mcp-wp/create-page`)

Example creating a page:
```bash
# Get session
SESSION=$(curl -k -s -i -X POST "https://your-site.com/wp-json/mcp/mcp-adapter-default-server" \
  -u "username:password" \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0"}}}' \
  | grep -i 'mcp-session-id' | cut -d' ' -f2 | tr -d '\r')

# Create page
curl -k -s -X POST "https://your-site.com/wp-json/mcp/mcp-adapter-default-server" \
  -u "username:password" \
  -H 'Content-Type: application/json' \
  -H "Mcp-Session-Id: $SESSION" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"mcp-wp-create-page","arguments":{"title":"My Page","content":"Page content","status":"publish"}}}'
```

## Version

Version: 1.0.0

## License

MIT License - see LICENSE file for details

## Contributing

Improvements and additional abilities welcome. Ensure:
- All abilities follow existing patterns
- Input/output schemas are complete
- Proper capability checks are in place
- Input validation is comprehensive
- Code follows WordPress coding standards
