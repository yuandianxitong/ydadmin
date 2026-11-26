export function pageKeyFromViewFile(rel) {
    return rel.replace(/\\/g, '/').replace(/\.vue$/i, '')
}
