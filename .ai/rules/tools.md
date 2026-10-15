---
paths:
  - 'app/Mcp/Tools/**'
---

# Tools

## MCP tools declare their own rules
An MCP tool declares its validation rules inline, like every FormRequest (see AGENTS.md "Backend Validation": requests are independent). Do not call a shared rules helper; the classes under app/Support/Requests/** were created in the same PR, before this rule; the owner will refactor them later, so no new code may use or extend them. Business rules live in Actions/policies so web, API and MCP enforce them identically, and the parity tests in tests/Feature/Parity prove the three surfaces agree.
