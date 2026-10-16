# REST API conventions

These conventions apply to new HTTP/JSON endpoints in BloodBankPlatform. They adapt [Google's API Design Guide](https://docs.cloud.google.com/apis/design) and the linked API Improvement Proposals (AIPs) to this project's REST API. Google-specific protobuf and gRPC requirements apply only if we introduce protobuf service definitions. Examples below illustrate the contract; they do not declare that an endpoint already exists.

## 1. Design around resources

- Model public concepts as resources and collections, not database tables or controller actions. Examples: `banks`, `hospitals`, `donors`, `donations`, and `components`.
- Give each resource a stable, unique `name` that is its relative resource path, without a leading slash or API version. For example, `banks/bank-123/donations/donation-456`. A response may also include an output-only `uid` when an internal immutable identifier is useful.
- Use plural, lower camel case collection segments. Alternate collection and ID segments, and nest a resource under its canonical parent only when that parent owns its lifecycle. Do not mirror every database foreign key in the URL.
- Treat `name` as the resource identifier. Use `displayName` for a human-readable label. Represent a link to another resource by its resource name, such as `bank: "banks/bank-123"`, rather than embedding the linked resource by default.
- Document allowed resource ID characters and length for each collection. Keep user-supplied IDs lowercase and URL-safe. Return canonical names even if an alias such as `users/me` is accepted on input.

See [AIP-121](https://google.aip.dev/121) and [AIP-122](https://google.aip.dev/122).

## 2. Paths, methods, and responses

Prefix stable routes with `/v1`. Use the same resource representation for Get, Create, and Update responses. Do not wrap a single resource in a generic `data` object.

| Operation | HTTP route | Success response | Purpose |
| --- | --- | --- | --- |
| List | `GET /v1/banks/{bank}/donations` | `200` with `donations` and `nextPageToken` | Read a collection. |
| Get | `GET /v1/banks/{bank}/donations/{donation}` | `200` with a Donation resource | Read one resource. |
| Create | `POST /v1/banks/{bank}/donations` | `200` with the created Donation resource | Add a resource to a collection. |
| Update | `PATCH /v1/banks/{bank}/donations/{donation}` | `200` with the updated Donation resource | Change selected fields. |
| Delete | `DELETE /v1/banks/{bank}/donations/{donation}` | `200` with an empty body | Remove a resource when deletion is allowed. |

- Use `GET` only for reads. Do not send a request body with `GET` or `DELETE`.
- Use `PATCH` for updates. Avoid `PUT` unless an endpoint is explicitly defined as full replacement. Pass an `updateMask` query parameter with comma-separated field paths, such as `?updateMask=displayName,contactEmail`; the body is the resource fields to update. An omitted mask means all populated, mutable fields in the body. An explicit empty mask is a no-op. Document how to clear a field and reject paths that are immutable or output-only.
- Create and Update return the resulting resource. Delete returns an empty body. If deletion is prohibited by domain rules, omit Delete and provide the appropriate lifecycle action instead.
- Do not turn a state transition with side effects into a generic field update. Use a custom method when the five standard methods cannot express the operation, for example `POST /v1/banks/{bank}/donations/{donation}:release`. Use lower camel case after the colon and `POST` for mutations. Prefer `GET` for read-only custom methods.
- Keep each request independent; no endpoint should depend on a prior request's session state. Require authentication on protected endpoints and enforce authorization for the specific resource and organization on every request.

See [AIP-131](https://google.aip.dev/131), [AIP-132](https://google.aip.dev/132), [AIP-133](https://google.aip.dev/133), [AIP-134](https://google.aip.dev/134), [AIP-135](https://google.aip.dev/135), and [AIP-136](https://google.aip.dev/136). The `200` codes above follow Google's HTTP/JSON mapping for synchronous standard methods; use an operation resource for genuinely long-running work.

## 3. JSON fields and data

- Use `application/json` and lower camel case JSON keys: `displayName`, `createTime`, `nextPageToken`. Use consistent names for the same concept throughout the API.
- Use RFC 3339 timestamps with a UTC offset, preferably `Z`, for instants. Name event timestamps `createTime`, `updateTime`, and similar. Use date-only strings such as `2026-10-16` only for calendar dates that have no time of day.
- Make units explicit for measured quantities, for example `volumeMilliliters`. Document enum values and include an unspecified value where absence is meaningful.
- Distinguish absent, `null`, and empty values in each resource schema. Treat `name` as the resource identifier and make `createTime` and `updateTime` output-only. Never include passwords, access tokens, or sensitive donor details in general list responses.
- Define resource schemas and endpoint contracts in OpenAPI before implementation; document required fields, validation, examples, permissions, and error cases there.

See [AIP-148](https://google.aip.dev/148), [AIP-190](https://google.aip.dev/190), and [AIP-127](https://google.aip.dev/127). The JSON and OpenAPI choices above are project conventions for a REST-only implementation.

## 4. List, filter, and pagination

Every List endpoint accepts optional `pageSize` and `pageToken` query parameters and returns a plural resource array plus `nextPageToken`:

```http
GET /v1/banks/bank-123/donations?pageSize=50&orderBy=createTime%20desc
```

```json
{
  "donations": [
    { "name": "banks/bank-123/donations/donation-456", "createTime": "2026-10-16T09:00:00Z" }
  ],
  "nextPageToken": "opaque-token"
}
```

- Document a default and maximum `pageSize` per endpoint. Treat omitted or zero as the default, cap values above the maximum, and reject negative values with `INVALID_ARGUMENT`.
- Treat page tokens as opaque. An empty or absent `nextPageToken` means there is no next page. Keep the same filter, ordering, and other query values when following a token; `pageSize` may change.
- If supported, use `filter` and `orderBy` query parameters. Document the permitted fields and operators. Use a stable tie-breaker in ordering so clients can paginate consistently.
- Return only resources visible to the caller. Apply organization scope and permissions before filtering and pagination.

See [AIP-132](https://google.aip.dev/132) and [AIP-158](https://google.aip.dev/158).

## 5. Errors

Use non-2xx HTTP status codes and Google's JSON error shape. `code` is the HTTP status number; `status` is a canonical error name. Include a machine-readable `ErrorInfo` entry in `details`, with a stable `reason` and service `domain`. Keep `message` brief, actionable, and safe to show to a developer; do not expose secrets or medical data.

```json
{
  "error": {
    "code": 400,
    "message": "The page size must be zero or greater.",
    "status": "INVALID_ARGUMENT",
    "details": [
      {
        "@type": "type.googleapis.com/google.rpc.ErrorInfo",
        "reason": "INVALID_PAGE_SIZE",
        "domain": "api.bloodbank.example"
      }
    ]
  }
}
```

Replace the illustrative `domain` with the deployed API's service domain. Use `BadRequest` details for field validation errors where useful. Typical mappings are `INVALID_ARGUMENT` → 400, `UNAUTHENTICATED` → 401, `PERMISSION_DENIED` → 403, `NOT_FOUND` → 404, `ALREADY_EXISTS` → 409, `FAILED_PRECONDITION` → 400, `RESOURCE_EXHAUSTED` → 429, and `UNAVAILABLE` → 503. Choose by meaning, not solely by HTTP number.

Check permission before disclosing whether a resource exists: return `PERMISSION_DENIED` (403) when access is not allowed, and `NOT_FOUND` (404) when the caller is authorized but the resource is absent. Apply this consistently to cross-organization access.

See [AIP-193](https://google.aip.dev/193).

## 6. Versioning and compatibility

- Put only the major version in the path: `/v1`, not `/v1.1` or a date. Add compatible fields and endpoints within a major version.
- Do not rename or remove an existing field, change its meaning or type, change a resource name pattern, or silently alter a method's behavior in `v1`. Review such changes as potential breaking changes.
- Introduce a new major version when an incompatible change is unavoidable. Run old and new versions together for a documented migration period.
- Document deprecations, replacement endpoints, and migration steps before removal.

See [AIP-180](https://google.aip.dev/180) and [AIP-185](https://google.aip.dev/185).
