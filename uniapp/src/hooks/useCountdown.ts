import { onBeforeUnmount, ref } from 'vue'

/**
 * 倒计时 composable
 *
 * 统一替代项目中多处重复实现的 "60 秒验证码倒计时"。
 * 自动在组件卸载时清理 timer，避免内存泄漏。
 *
 * 使用示例：
 * ```ts
 * const { countdown, isActive, start, clear } = useCountdown(60)
 *
 * async function handleSendCode() {
 *   if (isActive.value) return
 *   await authApi.sendSmsCode(...)
 *   start()
 * }
 * ```
 *
 * @param seconds 倒计时起始秒数，默认 60
 */
export function useCountdown(seconds = 60) {
  const countdown = ref(0)
  let timer: ReturnType<typeof setInterval> | null = null

  const isActive = ref(false)

  function clear() {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
    countdown.value = 0
    isActive.value = false
  }

  function start(duration = seconds) {
    clear()
    countdown.value = duration
    isActive.value = true
    timer = setInterval(() => {
      countdown.value--
      if (countdown.value <= 0) {
        clear()
      }
    }, 1000)
  }

  onBeforeUnmount(() => {
    clear()
  })

  return {
    countdown,
    isActive,
    start,
    clear,
  }
}
