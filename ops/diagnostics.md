# Production connection diagnostics

The host nginx writes request metadata to `/var/log/nginx/yatseck.diagnostic.log` for all application and static paths. Its `yatseck.error.log` uses the `info` level to record TLS handshake timeouts. The existing access log remains in place. Nginx logrotate keeps 14 daily files.

`yatseck-tls-capture.service` records the first 128 bytes of HTTPS packets on ports 443 and 8443 on the public interface. This is enough to compare TCP sequence numbers and read the declared TLS record length, while excluding page content. Root-only capture files rotate at 5 MB across four files (20 MB maximum) in `/var/log/yatseck-packets/`.

To inspect a reported connection, correlate the client's public IP, source port, and UTC time:

```sh
grep '"ip":"CLIENT_IP"' /var/log/nginx/yatseck.diagnostic.log
grep 'CLIENT_IP' /var/log/nginx/yatseck.error.log
tcpdump -nn -tttt -r /var/log/yatseck-packets/https.pcap 'host CLIENT_IP and (tcp port 443 or tcp port 8443)'
```

When there is no diagnostic access entry, inspect the packet capture and TLS error log. A completed TCP handshake followed by only the first part of a TLS record points to a failure before nginx can process an HTTP request. The packet capture is a rolling window and should not be treated as a long-term archive.

## iOS 26 connection incident, 2026-10-23

The iPhone 16 Pro Max running iOS 26.3.1 sent a TLS ClientHello with an X25519MLKEM768 key share. Its TLS record declared 1,533 bytes including the record header. On failed connections from the mobile network, the server's `eth0` capture and TCP socket received only the first 1,388 bytes; no HTTP request followed. Advertising a 1,200-byte TCP MSS changed the first received piece to 1,188 bytes but did not make the remaining piece arrive, so the temporary MSS rule was removed.

The iPhone SE running iOS 18.7.2 received HTTP 200 responses. Both observed mobile addresses were in AS8359 (MTS). The server address is in AS57043 (HOSTKEY). The host firewall accepts port 443, conntrack was far below capacity, and the virtual network interface reported zero receive drops.

A [large fragmented ClientHello test](https://github.com/dadrian/tldr.fail/blob/main/tldr_fail_test.py) returned a TLS ServerHello for both one-write and two-write cases, from an external machine and from server loopback. This rules out nginx failing to read a fragmented ClientHello in those tests. The missing piece could be lost in the network path or not sent by the client; a capture on the phone or provider edge would distinguish those cases. A different TLS termination address or provider-side investigation is needed to fix the public route. Changing nginx's preferred TLS group cannot shrink a ClientHello that the phone has already sent.

On 2026-10-23, an alternate HTTPS listener was added on port 8443 in the existing nginx server block. It serves the same site and certificate; this is a diagnostic workaround for a possible port-specific route failure, not a proven fix. The pre-change nginx backup is `/root/backups/nginx-yatseck.ru.before-alt-8443.20261023193210`. To remove the listener, delete its `listen 8443 ssl;` line, run `nginx -t`, and reload nginx. Check `https://yatseck.ru:8443/` from the affected phone without VPN and compare it to the usual URL; both ports are now captured.
