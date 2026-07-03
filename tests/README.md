# Tests for wordpress-wae

This folder now contains two complementary test tracks:

1. MCP ability tests (existing)
2. Agent integration smoke tests (new)

The existing category-based MCP tests are still relevant and were kept.
They validate the plugin abilities directly via MCP endpoint calls.

The new agent tests validate the running AI agent service through Socket.IO events so we can catch orchestration problems earlier.

## Test Layout

- tests/run-all.sh
  - Full MCP ability harness (posts, comments, media, etc.)
- tests/categories/
  - Category-specific MCP ability tests
- tests/lib/helpers.sh
  - Common MCP test helpers
- ../../../agent/tests/smoke/run-agent-tests.sh
  - Relocated wrapper script for agent smoke tests
- ../../../agent/tests/smoke/agent-socket-smoke.cjs
  - Relocated Socket.IO integration test client
- ../../../agent/tests/e2e/run-agent-mcp-e2e.sh
  - Relocated end-to-end workflow test (agent + MCP mutations + verification + cleanup)
- ../../../agent/tests/e2e/agent-mcp-e2e.cjs
  - Relocated Node E2E test that runs realistic usage flow
- ../../../agent/tests/e2e/sites.json
  - Relocated site definitions for MCP/agent test targets (kept in git-excluded agent tests)

## What the New Agent Smoke Test Verifies

By default (fast mode), the test checks:

1. Socket connection to the running agent
2. ensure_repo roundtrip for a site_id
3. get_history roundtrip for the same site_id
4. step timings (connect, ensure_repo, history, total)

Optional chat mode also checks:

4. chat_message roundtrip and a chat_reply response

This gives a practical baseline for latency and functional health without always invoking the full mutation flow.

## Prerequisites

1. Agent is running
2. Node.js available
3. Valid site ID in your local setup
4. socket.io-client available via agent node_modules (default path auto-detected)

## Quick Start

From repository root:

bash agent/tests/smoke/run-agent-tests.sh

Expected result: JSON output with ok true and timing metrics.

## Run With Chat Roundtrip (slower)

AGENT_CHAT_TEST=1 bash agent/tests/smoke/run-agent-tests.sh

## Full Realistic E2E (Agent + MCP)

This is the real usage flow test:

1. MCP list-pages
2. MCP create-page with CTA content
3. MCP search-replace-content
4. MCP get-page verification
5. Agent ensure_repo + get_history
6. Agent chat flow: create page (proposal + approve)
7. Agent chat flow: replace text on created page (proposal + approve)
8. MCP verification of agent mutation
9. Cleanup: delete created test pages
10. Model policy check: goose model must include `flash`

Run from repository root:

bash agent/tests/e2e/run-agent-mcp-e2e.sh

This requires `MCP_USERNAME` and `MCP_PASSWORD`.

Credentials can be loaded automatically from `tests/.env`.

Useful when you want to verify classifier/chat path end-to-end, not only transport/repo/history.

## Environment Variables

You can override all runtime settings:

- AGENT_DIR
  - Default: ../../../agent resolved from plugin root
- AGENT_URL
  - Default: https://127.0.0.1:5041
- AGENT_SITE_ID
  - Default: trading-dtwg9f4
- AGENT_TIMEOUT_MS
  - Default: 30000
- AGENT_INSECURE_TLS
  - Default: 1 (allows local self-signed certs)
- AGENT_CHAT_TEST
  - Default: 0
- AGENT_CHAT_MESSAGE
  - Default: Svara med exakt: OK
- AGENT_SOCKET_IO_CLIENT_PATH
  - Optional explicit override
- TEST_SITES_CONFIG
  - Default: agent/tests/e2e/sites.json
- TEST_SITE_KEY
  - Default: trading
- TEST_TIMEOUT_MS
  - Default: 120000 (used by E2E flow)
- AGENT_EXPECT_MODEL_SUBSTRING
  - Default: flash (E2E run fails if non-flash model detected for the socket run)

Example:

AGENT_SITE_ID=my-site-id AGENT_URL=https://127.0.0.1:5041 AGENT_CHAT_TEST=1 bash agent/tests/smoke/run-agent-tests.sh

E2E example:

TEST_SITE_KEY=trading AGENT_EXPECT_MODEL_SUBSTRING=flash bash agent/tests/e2e/run-agent-mcp-e2e.sh

## Defining Test Sites

Edit `agent/tests/e2e/sites.json`.

Example entry:

```json
{
  "defaultSite": "trading",
  "sites": {
    "trading": {
      "siteId": "trading-dtwg9f4",
      "siteUrl": "https://trading-dtwg9f4.mild-wp-sites.lndo.site/",
      "mcpEndpoint": "https://trading-dtwg9f4.mild-wp-sites.lndo.site/wp-json/mcp/mcp-adapter-default-server"
    }
  }
}
```

You can add multiple sites and choose one via `TEST_SITE_KEY`.

## Output and Interpretation

The Node smoke test prints a JSON summary like:

- ok
- context
- metrics
  - connectMs
  - ensureRepoMs
  - historyMs
  - chatMs (if enabled)
  - totalMs
- ensureRepo
- history

Use this to compare runs before and after changes in agent.js or MCP abilities.

## Troubleshooting

### Could not load socket.io-client

Set AGENT_DIR correctly or set AGENT_SOCKET_IO_CLIENT_PATH manually.

### Socket connect timeout

- Confirm agent is up
- Check URL and TLS mode
- Verify local port/proxy

### ensure_repo failed

- Confirm AGENT_SITE_ID exists in your local sites data path
- Check agent guardrails/site path resolution

### chat_message failed

- Re-run with AGENT_CHAT_TEST=0 to isolate transport/repo issues
- Inspect agent logs for goose/model or MCP errors

## Recommended Workflow

1. Run fast smoke test after each change:

- bash agent/tests/smoke/run-agent-tests.sh

2. If fast test passes, run chat smoke when needed:

- AGENT_CHAT_TEST=1 bash agent/tests/smoke/run-agent-tests.sh

3. For plugin correctness, run existing MCP harness as a separate lane:
   - bash tests/run-all.sh

This keeps agent orchestration validation and ability-level validation separate, fast, and easier to debug.
