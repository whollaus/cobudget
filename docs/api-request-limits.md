# Limit public API request bodies before Nextcloud

CoBudget accepts up to 65,536 bytes of JSON on its external v1 API (Beta). Configure the public web server to enforce the same limit before it forwards a body to PHP. Nextcloud can inspect requests during framework initialization, before CoBudget authenticates the key or checks the JSON size.

The examples cover both `/apps/cobudget/api/v1/…` and `/index.php/apps/cobudget/api/v1/…` on a Nextcloud installation at the domain root. For an installation under `/cloud`, include that prefix in the location pattern. Apply the same rule to every externally reachable hostname, route alias and front end serving this API. Keep the original upload limit on the rest of the site, including `/remote.php/dav/`.

## nginx reverse proxy

Add a location to the public HTTPS server, before other matching regex locations. In this example `nextcloud_backend` names the existing private Nextcloud HTTP upstream. Reuse the site's normal upstream, timeout settings and upstream TLS configuration, and repeat all trusted forwarding headers inside this `location` block (`nginx` drops inherited `proxy_set_header` directives from `server` or `http` whenever a `location` defines any `proxy_set_header`; omitting `X-Forwarded-For` can otherwise allow client IP spoofing behind `trusted_proxies`). Ensure nginx selects this location for both forms of the public URL, including repeated slashes when `merge_slashes off` is used or when the site defines a higher-priority prefix location.

```nginx
location ~ ^/+(?:index\.php/+)?apps/+cobudget/+api/+v1(?:/+|$) {
    client_max_body_size 65536;
    proxy_request_buffering on;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_pass http://nextcloud_backend;
}
```

Buffering makes nginx read the entire request body before it forwards the request, including HTTP/1.1 chunked bodies. The existing upstream handles Nextcloud's front-controller rewriting. For a direct nginx-to-PHP-FPM site, place the same size limit and `fastcgi_request_buffering on` in the API location that actually invokes the fixed Nextcloud `index.php` script. Internal redirects to a generic PHP location can change the active limit. Validate with `nginx -t` before a graceful reload. [nginx body limit](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size) · [Request buffering for HTTP upstreams](https://nginx.org/en/docs/http/ngx_http_proxy_module.html#proxy_request_buffering) · [Request buffering for FastCGI](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html#fastcgi_request_buffering)

## Apache HTTP Server 2.4 reverse proxy

For a public Apache virtual host that already forwards to a private Nextcloud HTTP backend using `mod_proxy_http`, enable `mod_request` and `mod_env`. Keep the existing `ProxyPass` and HTTPS configuration. Add the following to the virtual host.

```apache
<LocationMatch "^/+(?:index\.php/+)?apps/+cobudget/+api/+v1(?:/+|$)">
    LimitRequestBody 65536
    KeptBodySize 65536
    SetEnv proxy-sendcl 1
</LocationMatch>
```

`KeptBodySize` caps the request filter's in-memory copy. `proxy-sendcl` supplies the HTTP backend with a content length, including for client requests using chunked transfer. An Apache 2.4.67 loopback test with this combination returned 413 before the backend received the request. In the same reverse-proxy setup, using only `LimitRequestBody` and `proxy-sendcl` allowed an oversized request through. Validate with `apachectl configtest` and use a graceful reload. For installations running PHP directly in Apache or through `mod_proxy_fcgi`, a buffering public reverse proxy as shown above provides the tested boundary. [Apache request filter](https://httpd.apache.org/docs/2.4/mod/mod_request.html#keptbodysize) · [Apache body limit](https://httpd.apache.org/docs/2.4/mod/core.html#limitrequestbody) · [Apache HTTP proxy environment settings](https://httpd.apache.org/docs/2.4/mod/mod_proxy_http.html#envsettings)

## Verify the effective route

In **Administration settings → Overview**, Nextcloud runs the CoBudget request size check. It also runs with `occ setupchecks`. The check sends anonymous requests from the server to the address configured in `overwrite.cli.url`. It tests each available URL form with the two sizes and transfer types below. It warns separately if an oversized request reaches CoBudget or if the response is inconclusive, for example when DNS or TLS prevents the server from reaching its public URL.

A successful check confirms HTTP 413 on the tested route. The hosting provider can use upstream logs to verify where the request stopped and check additional public hosts or entry points.

On a test installation, send a single anonymous JSON POST to the v1 entries route for each cell. A body consisting of `{}` followed by spaces is valid JSON at either size. Omit credentials. Use the configured HTTPS host.

| Request transfer | 65,536 bytes | 65,537 bytes |
| --- | --- | --- |
| Known Content-Length | 401 from CoBudget | 413 at the web server |
| HTTP/1.1 chunked, no Content-Length | 401 from CoBudget | 413 at the web server |

Repeat for both URL forms. Confirm in the proxy and upstream logs that the backend received only the accepted-size requests. Test a normal Files upload beyond 64 KiB separately to check that ordinary Nextcloud uploads retain the site's configured allowance.
