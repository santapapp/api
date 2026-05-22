# Phase 2: Auth, Organisasi, Role, dan Context

[Roadmap](../../roadmap.md)

---

## Tujuan

Membangun fondasi produk yang paling penting: user login, organisasi, membership, role per organisasi, permission, dan organization context middleware.

## Referensi

- [Multi-Organisasi, Role, dan Permission](../../santap-api/01-multi-organization-and-permissions.md)
- [Autentikasi dan Session](../../santap-api/02-authentication-and-sessions.md)
- [Middleware dan Data Scoping](../../santap-api/06-middleware-and-data-scoping.md)
- [Keputusan Final dan Catatan Implementasi](../../santap-api/11-decisions-and-notes.md)

## Scope

- Model dan migration:
  - `Organization`
  - `OrganizationMember`
  - `OrganizationInvitation`
- Update model `User`.
- Seeder role dan permission awal.
- Sanctum login/logout/me.
- Endpoint list organisasi user.
- Endpoint switch/resolve organisasi aktif.
- Middleware:
  - `resolve.organization`
  - `ensure.organization.member`
  - `ensure.organization.permission`
- Policy awal untuk organization dan membership.
- Trait/helper `BelongsToOrganization`.

## Urutan Pengerjaan

1. Buat enum status:
   - `OrganizationStatus`
   - `MemberStatus`
   - `InvitationStatus`
2. Buat migration organisasi, member, dan invitation.
3. Buat relationship:
   - User many-to-many Organization.
   - Organization many-to-many User.
   - Organization has many members/invitations.
4. Konfigurasi Spatie Permission untuk role global dan role organisasi.
5. Buat seeder permission matrix awal.
6. Implement auth endpoint:
   - `POST /api/v1/auth/login`
   - `POST /api/v1/auth/logout`
   - `GET /api/v1/me`
   - `GET /api/v1/me/organizations`
7. Implement endpoint organization context:
   - `POST /api/v1/context/switch-organization`
8. Implement middleware organization context.
9. Implement invite flow dasar:
   - create invitation
   - accept invitation
   - expiry handling
10. Tambahkan feature test untuk login, membership, dan akses lintas organisasi.

## Deliverables

- User bisa login memakai Sanctum.
- Response login mengembalikan user, token, organisasi, dan role per organisasi.
- User hanya bisa memilih organisasi tempat dia menjadi member aktif.
- Endpoint bisnis bisa memakai `X-Organization-Id`.
- Query bisnis memiliki pola scoping yang konsisten.

## Acceptance Criteria

- User non-member ditolak saat memakai `X-Organization-Id` organisasi lain.
- Member suspended tidak bisa mengakses organisasi.
- Owner bisa invite user.
- Invite token punya expiry.
- Kitchen tidak bisa mengakses endpoint permission owner/cashier.
- Test membuktikan data lintas organisasi tidak bocor.

## Out of Scope

- Customer guest session.
- Menu dan meja.
- Payment.
- Realtime.

---

[Roadmap](../../roadmap.md)
