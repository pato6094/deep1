# Configurazione Webhook PayPal

## 1. Accedi al Developer Dashboard PayPal

- **Sandbox**: https://developer.paypal.com/developer/applications/
- **Produzione**: https://developer.paypal.com/developer/applications/

## 2. Seleziona la tua App

Vai alla tua applicazione esistente o creane una nuova.

## 3. Configura i Webhook

1. Nella sezione "FEATURES", clicca su "Add Webhook"
2. Inserisci l'URL del webhook:
   ```
   https://tuodominio.com/webhook_paypal.php
   ```

## 4. Seleziona gli Eventi

Seleziona questi eventi per il tracciamento degli abbonamenti:

### Eventi Obbligatori:
- ✅ `BILLING.SUBSCRIPTION.ACTIVATED`
- ✅ `BILLING.SUBSCRIPTION.CANCELLED` 
- ✅ `BILLING.SUBSCRIPTION.SUSPENDED`
- ✅ `BILLING.SUBSCRIPTION.EXPIRED`
- ✅ `PAYMENT.SALE.COMPLETED`
- ✅ `PAYMENT.SALE.DENIED`

### Eventi Opzionali (per monitoraggio avanzato):
- `BILLING.SUBSCRIPTION.CREATED`
- `BILLING.SUBSCRIPTION.UPDATED`
- `PAYMENT.SALE.PENDING`
- `PAYMENT.SALE.REFUNDED`

## 5. Salva e Ottieni le Credenziali

Dopo aver salvato il webhook, otterrai:
- **Webhook ID**: Necessario per la verifica della firma
- **Client Secret**: Necessario per l'autenticazione API

## 6. Aggiorna il Codice

Nel file `webhook_paypal.php`, aggiorna queste variabili:

```php
$paypal_client_secret = 'TUO_CLIENT_SECRET_QUI';
$paypal_webhook_id = 'TUO_WEBHOOK_ID_QUI';
```

## 7. Test del Webhook

### Test in Sandbox:
1. Usa il PayPal Webhook Simulator nel Developer Dashboard
2. Invia eventi di test al tuo endpoint
3. Verifica i log in `webhook_logs` nel database

### Test in Produzione:
1. Effettua un abbonamento di test
2. Cancella l'abbonamento dal tuo account PayPal
3. Verifica che lo stato venga aggiornato nel database

## 8. Monitoraggio

- Controlla regolarmente la tabella `webhook_logs` per errori
- Usa la pagina admin `admin/subscription_management.php` per monitorare gli eventi
- Imposta alert per webhook falliti

## 9. Sicurezza

⚠️ **IMPORTANTE**: La verifica della firma del webhook è attualmente disabilitata nel codice. 
Per la produzione, implementa la verifica della firma PayPal per garantire che i webhook provengano effettivamente da PayPal.

## 10. URL per Ambienti

### Sandbox:
- API Base: `https://api.sandbox.paypal.com`
- Webhook URL: `https://tuodominio.com/webhook_paypal.php`

### Produzione:
- API Base: `https://api.paypal.com`  
- Webhook URL: `https://tuodominio.com/webhook_paypal.php`

## Troubleshooting

### Webhook non ricevuti:
1. Verifica che l'URL sia raggiungibile pubblicamente
2. Controlla che non ci siano firewall che bloccano PayPal
3. Verifica i log del server web

### Errori di autenticazione:
1. Controlla che Client ID e Secret siano corretti
2. Verifica di usare le credenziali dell'ambiente giusto (sandbox/produzione)

### Eventi non processati:
1. Controlla la tabella `webhook_logs` per errori
2. Verifica che l'utente con il `subscription_id` esista nel database
3. Controlla i log PHP per errori di sintassi