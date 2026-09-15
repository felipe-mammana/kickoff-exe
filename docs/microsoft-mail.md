# Microsoft mail

Configure `config/microsoft.local.php` on the server. This file is ignored by Git.
Fill client_id, tenant_id and client_secret (the secret VALUE, not its ID).
Keep redirect_uri identical to the Web redirect registered in Entra.
For local testing, HTTP is accepted only for the exact host localhost, e.g.
`http://localhost:8080/?route=settings.microsoft.callback`. Open the application
on that same hostname before connecting. Public hosts require HTTPS.
Never commit credentials or share screenshots containing secrets.

Graph delegated permissions: User.Read, Mail.Send; the authorization request also
asks for offline_access. Implicit grants are not used. The application uses state,
PKCE and a ten-minute, single-use session-bound authorization challenge.

Open Settings > Email Microsoft as a system administrator, through the same
hostname registered in redirect_uri. Connect using the configured sender account.
Other accounts are rejected. Send test delivers only to the configured sender.

Tokens are encrypted using the existing CredentialCrypto key in app_settings.
Protect database backups and the encryption key. Never log token endpoint bodies.
On connection, EmailCode sends through Graph. Failures do not fall back to SMTP.
Disconnect removes locally stored tokens; revoke consent in Microsoft to invalidate
authorization there as well. Once disconnected, the existing SMTP configuration
is used again. Microsoft can require interactive reconnection after revocation or
policy changes. Monitor delivery and retain an alternative login recovery method.

The server needs PHP cURL, OpenSSL and a trusted CA bundle. Do not disable TLS
verification to work around a certificate error. Renew the client secret before
expiry. If the tunnel changes, update redirect_uri and the Entra Web registration.

Live verification requires tenant credentials and interactive consent. Verify:
- Viewer/editor denied settings.microsoft routes.
- POST connect/test/disconnect without valid CSRF is rejected.
- Expired, missing or reused callback state is rejected.
- Incorrect Microsoft sender cannot replace the active connection.
- Test email arrives; then exercise the normal 2FA email flow.
- Disconnect stops use of stored Microsoft tokens.
