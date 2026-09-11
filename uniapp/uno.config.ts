import { defineConfig } from 'unocss'
import presetWeapp from 'unocss-preset-weapp'
import presetIcons from '@unocss/preset-icons'
import { transformerClass } from 'unocss-preset-weapp/transformer'

const isH5 = process.env.UNI_PLATFORM === 'h5'

export default defineConfig({
  presets: [
    presetWeapp({ isH5, platform: 'uniapp' }),
    presetIcons({
      scale: 1.2,
      extraProperties: {
        'display': 'inline-block',
        'vertical-align': 'middle',
      },
    }),
  ],
  transformers: [
    transformerClass(),
  ],
})
