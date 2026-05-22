Create:
curl -X POST https://qris.sekeco.id/create -H "Content-Type: application/json" -d "{"order_id":"order-123","gross_amount":15000}"

Check:
curl "https://qris.sekeco.id/check?id=order-123"

Cancel:
curl -X DELETE "https://qris.sekeco.id/cancel?id=order-123"
