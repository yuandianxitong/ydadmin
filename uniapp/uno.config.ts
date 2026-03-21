import { defineConfig } from 'unocss'
import presetWeapp from 'unocss-preset-weapp'
import { transformerClass } from 'unocss-preset-weapp/transformer'

const isH5 = process.env.UNI_PLATFORM === 'h5'

export default defineConfig({
  presets: [
    presetWeapp({ isH5, platform: 'uniapp' }),
  ],
  transformers: [
    transformerClass(),
  ],
})
