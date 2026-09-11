import { getToken } from '~/composables/useRequest'

export default defineNuxtRouteMiddleware((to) => {
  if (!getToken()) {
    return navigateTo(`/login?redirect=${encodeURIComponent(to.fullPath)}`, { replace: true })
  }
})
