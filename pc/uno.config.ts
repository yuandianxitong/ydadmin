import {
  defineConfig,
  presetAttributify,
  presetIcons,
  presetTypography,
  transformerDirectives,
  transformerVariantGroup,
} from 'unocss'
import presetWind3 from '@unocss/preset-wind3'
import { FileSystemIconLoader } from '@iconify/utils/lib/loader/node-loaders'

export default defineConfig({
  presets: [
    presetWind3({ preflight: false }),
    presetAttributify(),
    presetIcons({
      extraProperties: {
        display: 'inline-block',
        width: '1em',
        height: '1em',
      },
      collections: {
        svg: FileSystemIconLoader('./assets/svg'),
      },
    }),
    presetTypography(),
  ],

  shortcuts: [
    ['btn', 'px-4 py-2 inline-flex items-center justify-center rounded-sm transition-all duration-200 cursor-pointer text-sm'],
    ['btn-primary', 'btn bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-hover)]'],
    ['btn-outline', 'btn border border-gray-300 text-gray-600 hover:text-[var(--color-primary)] hover:border-[var(--color-primary)]'],
    ['card', 'bg-white border border-gray-200 rounded-sm'],
    ['form-input', 'w-full px-3 py-2 border border-gray-300 rounded-sm text-sm bg-white focus:border-[var(--color-primary)] focus:outline-none transition-colors'],
    ['form-select', 'w-full px-3 py-2 border border-gray-300 rounded-sm text-sm bg-white focus:border-[var(--color-primary)] focus:outline-none transition-colors'],
  ],

  transformers: [transformerDirectives(), transformerVariantGroup()],
})
