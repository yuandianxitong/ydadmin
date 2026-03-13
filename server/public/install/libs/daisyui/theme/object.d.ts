interface Theme {
  "color-scheme": string
  "--color-base-100": string
  "--color-base-200": string
  "--color-base-300": string
  "--color-base-content": string
  "--color-primary": string
  "--color-primary-content": string
  "--color-secondary": string
  "--color-secondary-content": string
  "--color-accent": string
  "--color-accent-content": string
  "--color-neutral": string
  "--color-neutral-content": string
  "--color-info": string
  "--color-info-content": string
  "--color-success": string
  "--color-success-content": string
  "--color-warning": string
  "--color-warning-content": string
  "--color-error": string
  "--color-error-content": string
  "--radius-selector": string
  "--radius-field": string
  "--radius-box": string
  "--size-selector": string
  "--size-field": string
  "--border": string
  "--depth": string
  "--noise": string
}


interface Themes {
  retro: Theme
  fantasy: Theme
  aqua: Theme
  coffee: Theme
  lofi: Theme
  garden: Theme
  pastel: Theme
  halloween: Theme
  night: Theme
  cupcake: Theme
  dark: Theme
  emerald: Theme
  synthwave: Theme
  bumblebee: Theme
  light: Theme
  dracula: Theme
  sunset: Theme
  forest: Theme
  caramellatte: Theme
  corporate: Theme
  acid: Theme
  autumn: Theme
  business: Theme
  cyberpunk: Theme
  cmyk: Theme
  valentine: Theme
  dim: Theme
  abyss: Theme
  nord: Theme
  wireframe: Theme
  luxury: Theme
  silk: Theme
  black: Theme
  winter: Theme
  lemonade: Theme
  [key: string]: Theme
}

declare const themes: Themes
export default themes