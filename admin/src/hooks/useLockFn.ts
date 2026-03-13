import { ref } from 'vue'

/**
 * 锁函数钩子：用于在异步操作进行中锁住，避免重复调用
 * @param fn 传入一个异步函数
 */
export function useLockFn(fn: (...args: any[]) => Promise<any>) {
    const isLock = ref(false)

    const lockFn = async (...args: any[]) => {
        if (isLock.value) return
        isLock.value = true
        try {
            const res = await fn(...args)
            isLock.value = false
            return res
        } catch (e) {
            isLock.value = false
            throw e
        }
    }

    return {
        isLock,
        lockFn
    }
}
