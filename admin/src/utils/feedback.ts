import {
    ElLoading,
    ElMessage,
    ElMessageBox,
    type ElMessageBoxOptions,
    ElNotification
} from 'element-plus'
import type { LoadingInstance } from 'element-plus/es/components/loading/src/loading'

import { t } from '@/utils/i18n'

export class Feedback {
    private loadingInstance: LoadingInstance | null = null
    static instance: Feedback | null = null
    static getInstance() {
        return this.instance ?? (this.instance = new Feedback())
    }
    // 消息提示
    msg(msg: string) {
        ElMessage.info(msg)
    }
    // 错误消息
    msgError(msg: string) {
        ElMessage.error(msg)
    }
    // 成功消息
    msgSuccess(msg: string) {
        ElMessage.success(msg)
    }
    // 警告消息
    msgWarning(msg: string) {
        ElMessage.warning(msg)
    }
    // 弹出提示
    alert(msg: string) {
        ElMessageBox.alert(msg, t('feedback.systemTip'))
    }
    // 错误提示
    alertError(msg: string) {
        ElMessageBox.alert(msg, t('feedback.systemTip'), { type: 'error' })
    }
    // 成功提示
    alertSuccess(msg: string) {
        ElMessageBox.alert(msg, t('feedback.systemTip'), { type: 'success' })
    }
    // 警告提示
    alertWarning(msg: string) {
        ElMessageBox.alert(msg, t('feedback.systemTip'), { type: 'warning' })
    }
    // 通知提示
    notify(msg: string) {
        ElNotification.info(msg)
    }
    // 错误通知
    notifyError(msg: string) {
        ElNotification.error(msg)
    }
    // 成功通知
    notifySuccess(msg: string) {
        ElNotification.success(msg)
    }
    // 警告通知
    notifyWarning(msg: string) {
        ElNotification.warning(msg)
    }
    // 确认窗体
    confirm(msg: string) {
        return ElMessageBox.confirm(msg, t('feedback.friendlyTip'), {
            confirmButtonText: t('common.confirm'),
            cancelButtonText: t('common.cancel'),
            type: 'warning'
        })
    }
    // 提交内容
    prompt(content: string, title: string, options?: ElMessageBoxOptions) {
        return ElMessageBox.prompt(content, title, {
            confirmButtonText: t('common.confirm'),
            cancelButtonText: t('common.cancel'),
            ...options
        })
    }
    // 打开全局loading
    loading(msg: string) {
        this.loadingInstance = ElLoading.service({
            lock: true,
            text: msg
        })
    }
    // 关闭全局loading
    closeLoading() {
        this.loadingInstance?.close()
    }
}

const feedback = Feedback.getInstance()

export default feedback
