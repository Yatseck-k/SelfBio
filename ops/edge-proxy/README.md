# Russian edge VPS for yatseck.ru

This is a deployment plan for a **separate** VPS with a public IPv4 address in Russia. It has not been ordered or activated. The edge terminates browser TLS and proxies HTTPS to the existing German origin at `217.11.166.118`. Keep the application, database and workers on the origin.

1. Provision an Ubuntu VPS with nginx, certbot and the system CA bundle. Allow inbound TCP 80/443 and outbound TCP 443 to the origin.
2. Securely copy the current `fullchain.pem` and `privkey.pem` for `yatseck.ru` from the origin to `/etc/nginx/tls/yatseck/` on the edge (directory mode 0700, private key 0600). Do not put either file in Git. The current certificate covers apex and `www` and expires on 2027-01-13.
3. Install `nginx-yatseck.conf` as an enabled site. Create `/var/www/letsencrypt/.well-known/acme-challenge`; run `nginx -t` and reload. Test both names against the edge IP with `curl --resolve` from several networks, including a phone on mobile data. Check pages, CSS, JS, images, forms, redirects and admin sign-in.
4. Once the edge works, set the authoritative DNS A records for `yatseck.ru` and `www.yatseck.ru` to the edge IPv4. Preserve all other DNS records. Verify against the authoritative AdminVPS nameservers and wait for the previous 3600-second TTL to expire. Keep the origin's 443 listener open for the edge and for rollback.
5. After DNS cutover, issue an independently renewed edge certificate with `certbot certonly --webroot -w /var/www/letsencrypt -d yatseck.ru -d www.yatseck.ru --cert-name yatseck.ru`. Update both TLS paths in the nginx site to `/etc/letsencrypt/live/yatseck.ru/{fullchain,privkey}.pem`, test/reload nginx, and install a certbot deploy hook to reload nginx. Check `certbot renew --dry-run`.
6. Keep the origin certificate renewal working while it terminates edge-to-origin HTTPS. With public DNS on the edge, its current HTTP-01 renewal may require the edge to proxy the origin's challenge path or a switch to DNS-01. Check the origin's renewal configuration and solve this before its certificate expires.
7. Watch the edge access/error logs and the origin diagnostic log. The origin will see the edge as its peer. Only if client IP attribution is needed, configure nginx `set_real_ip_from` for the exact edge IP and `real_ip_header X-Real-IP` on the origin. Never trust forwarded IP headers from arbitrary clients.

Rollback: restore the two A records to `217.11.166.118`. Because the old DNS TTL is 3600 seconds, some clients may keep using the edge until their cache expires; leave it online through that interval.
