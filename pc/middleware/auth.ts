import { getToken } from '~/composables/useRequest'

export default defineNuxtRouteMiddleware(() => {
  if (!getToken()) {
    return navigateTo('/login', { replace: true })
  }
})
