import {
  defineConfig,
  presetAttributify,
  presetIcons,
  presetTypography,
  transformerDirectives,
  transformerVariantGroup,
} from 'unocss'
import presetWind3 from '@unocss/preset-wind3'

export default defineConfig({
  presets: [
    presetWind3(),
    presetAttributify(),
    presetIcons({
      extraProperties: {
        display: 'inline-block',
        width: '1em',
        height: '1em',
      },
    }),
    presetTypography(),
  ],

  shortcuts: [
    ['btn', 'px-4 py-2 inline-flex items-center justify-center rounded-md transition-all duration-200 cursor-pointer'],
    ['btn-primary', 'btn bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800'],
    ['btn-outline', 'btn border border-gray-300 text-gray-700 hover:bg-gray-50'],
    ['card', 'bg-white border border-gray-200 rounded-lg shadow-sm'],
  ],

  transformers: [transformerDirectives(), transformerVariantGroup()],
})
