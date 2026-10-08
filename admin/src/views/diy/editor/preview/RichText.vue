<template>
  <div class="pv-rich" v-html="html"></div>
</template>
<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ props: Record<string, any> }>()

const ALLOWED = new Set([
  'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
  'h1', 'h2', 'h3', 'h4', 'blockquote', 'span', 'div',
  'table', 'thead', 'tbody', 'tr', 'th', 'td', 'a', 'img',
])
const DROP = new Set(['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'svg', 'math', 'form'])
const URL_ATTRS = new Set(['href', 'src'])
const KEEP: Record<string, Set<string>> = {
  a: new Set(['href', 'title']),
  img: new Set(['src', 'alt']),
}

function safeUrl(url: string): boolean {
  const value = url.trim()
  if (value === '' || /[\u0000-\u0020]/.test(value)) return false
  if (/^(https?:|mailto:|tel:)/i.test(value)) return true
  if (/^[a-z][a-z0-9+.-]*:/i.test(value)) return false
  return !value.startsWith('//')
}

function sanitize(raw: string): string {
  if (!raw || !raw.includes('<')) return raw
  const doc = new DOMParser().parseFromString(raw, 'text/html')
  const walk = (node: Node) => {
    const children = Array.from(node.childNodes)
    children.forEach((child) => {
      if (child.parentNode) walk(child)
    })
    if (!(node instanceof Element) || node === doc.body) return
    const tag = node.tagName.toLowerCase()
    const parent = node.parentNode
    if (!parent) return
    if (DROP.has(tag)) {
      parent.removeChild(node)
      return
    }
    if (!ALLOWED.has(tag)) {
      while (node.firstChild) parent.insertBefore(node.firstChild, node)
      parent.removeChild(node)
      return
    }
    const keep = KEEP[tag] ?? new Set<string>()
    Array.from(node.attributes).forEach((attr) => {
      const name = attr.name.toLowerCase()
      if (name.startsWith('on') || !keep.has(name) || (URL_ATTRS.has(name) && !safeUrl(attr.value))) {
        node.removeAttribute(attr.name)
      }
    })
  }
  walk(doc.body)
  return doc.body.innerHTML
}

const html = computed(() => {
  const content = props.props?.content
  if (typeof content !== 'string' || content === '') {
    return '<span style="color:#9aa4b2">富文本</span>'
  }
  return sanitize(content)
})
</script>
<style scoped>.pv-rich { padding:10px; }</style>
