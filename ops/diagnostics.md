# Production connection diagnostics

The host nginx writes request metadata to `/var/log/nginx/yatseck.diagnostic.log` for all application and static paths. Its `yatseck.error.log` uses the `info` level to record TLS handshake timeouts. The existing access log remains in place. Nginx logrotate keeps 14 daily files.

`yatseck-tls-capture.service` records the first 128 bytes of HTTPS packets on the public interface. This is enough to compare TCP sequence numbers and read the declared TLS record length, while excluding page content. Root-only capture files rotate at 5 MB across four files (20 MB maximum) in `/var/log/yatseck-packets/`.

To inspect a reported connection, correlate the client's public IP, source port, and UTC time:

```sh
grep '"ip":"CLIENT_IP"' /var/log/nginx/yatseck.diagnostic.log
grep 'CLIENT_IP' /var/log/nginx/yatseck.error.log
tcpdump -nn -tttt -r /var/log/yatseck-packets/https.pcap 'host CLIENT_IP and tcp port 443'
```

When there is no diagnostic access entry, inspect the packet capture and TLS error log. A completed TCP handshake followed by only the first part of a TLS record points to a failure before nginx can process an HTTP request. The packet capture is a rolling window and should not be treated as a long-term archive.
