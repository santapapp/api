# Realtime, Queue, dan Admin Panel

[Indeks Santap API](../santap-api.md)

---

## 13. Realtime Event

Gunakan Laravel Reverb untuk realtime.

### 13.1 Event Utama

```txt
OrderCreated
OrderAccepted
OrderStatusUpdated
OrderItemStatusUpdated
OpenBillCreated
OpenBillRepeatOrderCreated
OpenBillClosed
PaymentCreated
PaymentPaid
CashierCalled
TableStatusChanged
```

### 13.2 Channel Realtime

```txt
private-org.{organizationId}
private-kitchen.{organizationId}
private-cashier.{organizationId}
private-table.{organizationId}.{tableId}
private-customer-session.{sessionId}
```

Ketentuan:

- Flutter owner/cashier/kitchen subscribe sesuai role.
- Customer subscribe ke channel session/table miliknya.
- Channel private harus divalidasi di Laravel broadcasting auth.

### 13.3 Contoh Event Flow

```txt
Customer membuat order
→ OrderCreated event
→ broadcast ke private-kitchen.{organizationId}
→ broadcast ke private-cashier.{organizationId}
→ kitchen screen update realtime
→ cashier screen update realtime
```

Untuk Open Bill, repeat order tetap berada pada satu row `orders`. Event realtime yang disiapkan adalah `OpenBillRepeatOrderCreated`, dengan payload batch seperti `batch_uuid`, `batch_number`, `items_count`, `batch_total`, `order_total`, `bill_status`, dan informasi meja ringkas.

---

## 14. Queue dan Job

Gunakan queue untuk proses yang tidak wajib blocking request.

Job awal:

```txt
SendOrganizationInvitationEmail
BroadcastOrderCreated
GenerateQrCodeImage
GenerateDailySalesReport
SendPaymentReceipt
CloseExpiredCustomerSessions
ExpirePendingInvitations
```

Queue yang disarankan:

```txt
default
notifications
broadcasts
reports
```

Gunakan Horizon untuk monitoring queue.

---

## 15. Filament Admin Panel

Panel admin Santap:

```txt
/admin
```

### 15.1 Akses

Hanya role global:

```txt
administrator
```

### 15.2 Resource Awal

```txt
UserResource
OrganizationResource
OrganizationMemberResource
PlanResource
SubscriptionResource
OrderResource readonly/support
PaymentResource readonly/support
ActivityLogResource
SystemSettingResource
```

### 15.3 Dashboard Widget

```txt
Total organizations
Active organizations
Suspended organizations
Total users
Total orders today
Total transactions today
Revenue summary
Recent activity logs
Failed jobs
```

### 15.4 Admin Action Sensitif

Action yang wajib masuk audit log:

```txt
Suspend organization
Activate organization
Change organization plan
Force remove member
Reset QR token
View organization support data
Void payment by admin
```

---

---

[Indeks Santap API](../santap-api.md)
