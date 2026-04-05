<template>
  <view class="d-phone-login">
    <view class="d-phone-login__field">
      <u--input
        v-model="mobile"
        type="number"
        maxlength="11"
        :placeholder="placeholder"
        clearable
        border="none"
        :customStyle="{ flex: 1 }"
      />
      <u-button
        type="primary"
        size="small"
        :disabled="!isMobileValid || countdown > 0"
        :loading="sendingCode"
        :customStyle="{ marginLeft: '20rpx', flexShrink: 0 }"
        @click="handleSendCode"
      >
        {{ countdown > 0 ? `${countdown}s` : '获取验证码' }}
      </u-button>
    </view>

    <view class="d-phone-login__field">
      <u--input
        v-model="code"
        type="number"
        :maxlength="codeLength"
        placeholder="请输入验证码"
        clearable
        border="none"
        :customStyle="{ flex: 1 }"
      />
    </view>

    <view class="d-phone-login__action">
      <u-button
        type="primary"
        block
        round
        :loading="logging"
        :disabled="!canSubmit || logging"
        @click="handleLogin"
      >
        登录
      </u-button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, computed, onBeforeUnmount } from 'vue'
import { authApi } from '@/api/auth'
import type { LoginResult } from '@/types/api'

const props = withDefaults(defineProps<{
  placeholder?: string
  codeLength?: number
}>(), {
  placeholder: '请输入手机号',
  codeLength: 6,
})

const emit = defineEmits<{
  success: [data: LoginResult]
  fail: [error: any]
}>()

const mobile = ref('')
const code = ref('')
const countdown = ref(0)
const sendingCode = ref(false)
const logging = ref(false)
let timer: ReturnType<typeof setInterval> | null = null

const isMobileValid = computed(() => /^1[3-9]\d{9}$/.test(mobile.value))
const canSubmit = computed(() => isMobileValid.value && code.value.length === props.codeLength)

function startCountdown() {
  countdown.value = 60
  timer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      clearTimer()
    }
  }, 1000)
}

function clearTimer() {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

async function handleSendCode() {
  if (!isMobileValid.value || countdown.value > 0 || sendingCode.value) return
  sendingCode.value = true

  try {
    await authApi.sendSmsCode({ mobile: mobile.value })
    uni.showToast({ title: '验证码已发送', icon: 'success' })
    startCountdown()
  } catch (err: any) {
    emit('fail', err)
  } finally {
    sendingCode.value = false
  }
}

async function handleLogin() {
  if (!canSubmit.value || logging.value) return
  logging.value = true

  try {
    const res = await authApi.smsLogin({
      mobile: mobile.value,
      code: code.value,
    })
    emit('success', res as unknown as LoginResult)
  } catch (err: any) {
    emit('fail', err)
  } finally {
    logging.value = false
  }
}

onBeforeUnmount(() => {
  clearTimer()
})
</script>

<style lang="scss" scoped>
.d-phone-login {
  padding: 20rpx 0;

  &__field {
    display: flex;
    align-items: center;
    margin-bottom: 30rpx;
  }

  &__action {
    margin-top: 40rpx;
  }
}
</style>
