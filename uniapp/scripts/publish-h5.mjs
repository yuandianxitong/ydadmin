import { access, cp, mkdir, rm } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'

const source = fileURLToPath(new URL('../dist/build/h5/', import.meta.url))
const target = fileURLToPath(new URL('../../server/public/mobile/', import.meta.url))
await access(source)
await rm(target, { recursive: true, force: true })
await mkdir(target, { recursive: true })
await cp(source, target, { recursive: true })
console.log('H5 已发布到 server/public/mobile/')
