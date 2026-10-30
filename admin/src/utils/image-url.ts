interface ImageUrlOptions {
    development: boolean
    origin: string
    apiBaseUrl?: string
    storageDomain?: string
    siteUrl?: string
}

function joinUrl(base: string, path: string): string {
    return `${base.replace(/\/+$/, '')}/${path.replace(/^\/+/, '')}`
}

/** Resolve display URLs without changing the paths saved in decoration data. */
export function resolveImageUrl(value: string, options: ImageUrlOptions): string {
    if (!value) return ''
    if (/^(?:data:|blob:|\/\/)/i.test(value)) return value

    let path = value
    if (/^https?:\/\//i.test(value)) {
        try {
            const url = new URL(value)
            const loopback = ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname)
            if (!loopback || !/^\/(?:static|storage)\//.test(url.pathname)) return value
            path = `${url.pathname}${url.search}${url.hash}`
        } catch {
            return value
        }
    }

    path = `/${path.replace(/^\/+/, '')}`
    // UniApp bundles these icons at /static/tabbar; the server keeps them under diy.
    path = path.replace(
        /^\/static\/tabbar\/((?:home|discover|message|my)(?:-active)?\.png)(?=[?#]|$)/,
        '/static/diy/tabbar/$1'
    )
    if (/^\/(?:static|storage)\//.test(path)) {
        // These resources belong to server/public, regardless of site/OSS settings.
        return options.development ? path : joinUrl(options.apiBaseUrl || options.origin, path)
    }
    if (options.development) return path
    return joinUrl(
        options.storageDomain || options.siteUrl || options.apiBaseUrl || options.origin,
        path
    )
}
