import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'
import test from 'node:test'
import ts from 'typescript'
import { ref, reactive, computed } from 'vue'
import { parse } from 'vue/compiler-sfc'

const root = new URL('../', import.meta.url)

function load(relative, { imports = {}, uni = {}, expose = '' } = {}) {
  const filename = fileURLToPath(new URL(relative, root))
  let source = readFileSync(filename, 'utf8')
  if (relative.endsWith('.vue')) source = parse(source).descriptor.scriptSetup.content
  source = source.replaceAll('import.meta.env', '({ VITE_APP_API_URL: "" })')
  const code = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 },
  }).outputText
  const exports = {}
  return vm.runInNewContext(code + '\n;' + (expose || 'exports'), {
    exports, uni,
    navigator: { userAgent: 'test', language: 'en-US' },
    require: name => {
      assert.ok(name in imports, 'Unexpected import: ' + name)
      return imports[name]
    },
  }, { filename })
}

const flush = () => new Promise(resolve => setImmediate(resolve))

test('notice taps preserve configured links and provide list/detail destinations', () => {
  const { resolveNoticeLink } = load('src/components/diy/notice.ts')
  assert.equal(resolveNoticeLink({ link: ' /modules/about/pages/about ', id: 7 }), '/modules/about/pages/about')
  assert.equal(resolveNoticeLink({ id: 7 }), '/modules/announcement/pages/announcement-detail?id=7')
  assert.equal(resolveNoticeLink({ link: '', id: -1 }), '/modules/announcement/pages/announcement-list')
  assert.equal(resolveNoticeLink(), '/modules/announcement/pages/announcement-list')
})

test('profile save trims the nickname and rejects an empty value', async () => {
  let mounted
  const saved = []
  const toasts = []
  const state = load('src/modules/user/pages/edit-profile.vue', {
    imports: {
      vue: { ref, reactive, computed, onMounted: callback => { mounted = callback } },
      '../composables/useUser': {
        useUser: () => ({
          loading: ref(false),
          loadProfile: async () => ({ nickname: '旧昵称', gender: 1 }),
          updateProfile: async data => saved.push(data),
        }),
      },
    },
    uni: { showToast: options => toasts.push(options.title) },
    expose: '({ form, handleSave })',
  })
  await mounted()
  state.form.nickname = '  新昵称  '
  await state.handleSave()
  assert.equal(saved[0].nickname, '新昵称')
  state.form.nickname = '   '
  await state.handleSave()
  assert.equal(saved.length, 1)
  assert.deepEqual(toasts, ['请输入昵称'])
})

test('app starts in Chinese with a bundled font instead of the CDN loader', () => {
  const fontUtil = { params: { loaded: false } }
  const locales = []
  const app = { use() { assert.equal(fontUtil.params.loaded, true) } }
  const { createApp } = load('src/main.ts', {
    imports: {
      'virtual:uno.css': {},
      vue: { createSSRApp: () => app },
      pinia: { createPinia: () => ({}) },
      'uview-plus': { default: {}, fontUtil },
      './App.vue': { default: {} },
    },
    uni: { setLocale: locale => locales.push(locale) },
  })
  createApp()
  assert.deepEqual(locales, ['zh-Hans'])
  const css = readFileSync(new URL('src/static/fonts/uview-icons.css', root), 'utf8')
  const encoded = css.match(/url\("data:font\/ttf;base64,([A-Za-z0-9+/=]+)"\)/)?.[1]
  assert.ok(encoded, 'Built-in font must be available without a network request')
  assert.equal(Buffer.from(encoded, 'base64').subarray(0, 4).toString('hex'), '00010000')
})

test('API errors request Chinese even when the device language is English', async () => {
  const toasts = []
  let sent
  const { http } = load('src/utils/request.ts', {
    imports: { './auth': { getToken: () => 'test-token', removeToken: () => {} } },
    uni: {
      request(options) {
        sent = options
        options.success({ statusCode: 200, data: { code: 400, message: '短信服务未配置' } })
      },
      showToast: options => toasts.push(options.title),
    },
  })
  await assert.rejects(http.post('/api/auth/sms-code', { mobile: '13800000000' }), /短信服务未配置/)
  assert.equal(sent.header['think-lang'], 'zh-cn')
  assert.equal(sent.header.Authorization, 'Bearer test-token')
  assert.deepEqual(toasts, ['短信服务未配置'])
})

test('agreement defaults to user terms and recovers from a failed load', async () => {
  let onLoad
  let fail = true
  const requested = []
  const titles = []
  const state = load('src/modules/agreement/pages/agreement.vue', {
    imports: {
      vue: { ref },
      '@dcloudio/uni-app': { onLoad: callback => { onLoad = callback } },
      '@/api/agreement': {
        agreementApi: {
          async getByCode(code) {
            requested.push(code)
            if (fail) throw new Error('offline')
            return { title: '用户协议', content: '<p>协议正文</p>' }
          },
        },
      },
    },
    uni: { setNavigationBarTitle: options => titles.push(options.title) },
    expose: '({ agreement, loading, errorMessage, loadAgreement })',
  })
  onLoad({})
  await flush()
  assert.equal(state.loading.value, false)
  assert.match(state.errorMessage.value, /加载失败/)
  fail = false
  await state.loadAgreement()
  assert.equal(state.errorMessage.value, '')
  assert.equal(state.agreement.value.content, '<p>协议正文</p>')
  assert.deepEqual(requested, ['user_agreement', 'user_agreement'])
  assert.deepEqual(titles, ['用户协议'])
})

test('legacy agreement routes preserve privacy links without changing the launch page', () => {
  let onLoad
  const destinations = []
  load('src/pages/user_agreement/agreement.vue', {
    imports: { '@dcloudio/uni-app': { onLoad: callback => { onLoad = callback } } },
    uni: { redirectTo: options => destinations.push(options.url) },
  })
  onLoad({})
  onLoad({ code: 'privacy_policy' })
  assert.deepEqual(destinations, [
    '/modules/agreement/pages/agreement?code=user_agreement',
    '/modules/agreement/pages/agreement?code=privacy_policy',
  ])
  const pages = JSON.parse(readFileSync(new URL('src/pages.json', root), 'utf8'))
  assert.equal(pages.pages[0].path, 'pages/index/index')
  assert.ok(pages.pages.some(page => page.path === 'pages/user_agreement/agreement'))
})
