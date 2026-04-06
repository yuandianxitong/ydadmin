<template>
    <div class="cron-builder">
        <!-- 模式切换 -->
        <div class="cron-builder__header">
            <span class="cron-builder__label">{{
                mode === 'simple' ? '简单模式' : '高级模式'
            }}</span>
            <el-switch
                v-model="isAdvanced"
                inline-prompt
                active-text="高级"
                inactive-text="简单"
                @change="handleModeChange"
            />
        </div>

        <!-- 简单模式 -->
        <div v-if="mode === 'simple'" class="cron-builder__simple">
            <!-- 频率选择 -->
            <el-select
                v-model="frequency"
                placeholder="请选择执行频率"
                style="width: 100%"
                @change="handleFrequencyChange"
            >
                <el-option label="每分钟" value="minute" />
                <el-option label="每N分钟" value="everyNMinute" />
                <el-option label="每小时" value="hour" />
                <el-option label="每N小时" value="everyNHour" />
                <el-option label="每天" value="day" />
                <el-option label="每周" value="week" />
                <el-option label="每月" value="month" />
            </el-select>

            <!-- 动态参数 -->
            <div class="cron-builder__params">
                <!-- 每N分钟 -->
                <div v-if="frequency === 'everyNMinute'" class="cron-builder__param-row">
                    <span>每</span>
                    <el-input-number
                        v-model="interval"
                        :min="1"
                        :max="59"
                        size="default"
                        controls-position="right"
                    />
                    <span>分钟执行一次</span>
                </div>

                <!-- 每小时 -->
                <div v-if="frequency === 'hour'" class="cron-builder__param-row">
                    <span>每小时第</span>
                    <el-select v-model="minute" style="width: 100px">
                        <el-option
                            v-for="m in 60"
                            :key="m - 1"
                            :label="`${m - 1} 分`"
                            :value="m - 1"
                        />
                    </el-select>
                    <span>执行</span>
                </div>

                <!-- 每N小时 -->
                <div v-if="frequency === 'everyNHour'" class="cron-builder__param-row">
                    <span>每</span>
                    <el-input-number
                        v-model="interval"
                        :min="1"
                        :max="23"
                        size="default"
                        controls-position="right"
                    />
                    <span>小时，第</span>
                    <el-select v-model="minute" style="width: 100px">
                        <el-option
                            v-for="m in 60"
                            :key="m - 1"
                            :label="`${m - 1} 分`"
                            :value="m - 1"
                        />
                    </el-select>
                    <span>执行</span>
                </div>

                <!-- 每天 -->
                <div v-if="frequency === 'day'" class="cron-builder__param-row">
                    <span>每天</span>
                    <el-time-picker
                        v-model="timeValue"
                        format="HH:mm"
                        placeholder="选择时间"
                        style="width: 140px"
                        @change="handleTimeChange"
                    />
                    <span>执行</span>
                </div>

                <!-- 每周 -->
                <div v-if="frequency === 'week'" class="cron-builder__param-group">
                    <div class="cron-builder__param-row">
                        <span>选择星期：</span>
                    </div>
                    <el-checkbox-group v-model="weekdays" class="cron-builder__checkbox-group">
                        <el-checkbox v-for="(label, idx) in weekdayLabels" :key="idx" :value="idx">
                            {{ label }}
                        </el-checkbox>
                    </el-checkbox-group>
                    <div class="cron-builder__param-row">
                        <span>执行时间：</span>
                        <el-time-picker
                            v-model="timeValue"
                            format="HH:mm"
                            placeholder="选择时间"
                            style="width: 140px"
                            @change="handleTimeChange"
                        />
                    </div>
                </div>

                <!-- 每月 -->
                <div v-if="frequency === 'month'" class="cron-builder__param-group">
                    <div class="cron-builder__param-row">
                        <span>选择日期：</span>
                    </div>
                    <el-checkbox-group
                        v-model="monthDays"
                        class="cron-builder__checkbox-group cron-builder__checkbox-group--days"
                    >
                        <el-checkbox v-for="d in 31" :key="d" :value="d"> {{ d }}日 </el-checkbox>
                    </el-checkbox-group>
                    <div class="cron-builder__param-row">
                        <span>执行时间：</span>
                        <el-time-picker
                            v-model="timeValue"
                            format="HH:mm"
                            placeholder="选择时间"
                            style="width: 140px"
                            @change="handleTimeChange"
                        />
                    </div>
                </div>
            </div>

            <!-- 预览 -->
            <div class="cron-builder__preview">
                <div class="cron-builder__preview-expression">
                    <span class="cron-builder__preview-label">Cron 表达式：</span>
                    <code>{{ generatedExpression }}</code>
                </div>
                <div class="cron-builder__preview-desc">
                    <span class="cron-builder__preview-label">执行说明：</span>
                    <span>{{ nextRunDescription }}</span>
                </div>
            </div>
        </div>

        <!-- 高级模式 -->
        <div v-else class="cron-builder__advanced">
            <el-input
                v-model="advancedExpression"
                placeholder="分 时 日 月 周（例: */5 * * * *）"
                maxlength="100"
                @input="handleAdvancedInput"
            >
                <template #append>
                    <el-tooltip placement="top">
                        <template #content>
                            <div style="line-height: 1.8">
                                <div>Cron 表达式格式：分 时 日 月 周</div>
                                <div>* 表示任意值</div>
                                <div>*/N 表示每隔 N</div>
                                <div>1,3,5 表示指定多个值</div>
                                <div>1-5 表示范围</div>
                                <div>示例：0 2 * * * 每天凌晨2点</div>
                            </div>
                        </template>
                        <el-icon><QuestionFilled /></el-icon>
                    </el-tooltip>
                </template>
            </el-input>
        </div>
    </div>
</template>

<script setup lang="ts">
import { QuestionFilled } from '@element-plus/icons-vue'
import { computed, ref, watch } from 'vue'

type Frequency = 'minute' | 'everyNMinute' | 'hour' | 'everyNHour' | 'day' | 'week' | 'month'

const props = defineProps<{
    modelValue: string
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

// 内部状态
const mode = ref<'simple' | 'advanced'>('simple')
const isAdvanced = ref(false)
const frequency = ref<Frequency>('minute')
const minute = ref(0)
const hour = ref(0)
const interval = ref(5)
const weekdays = ref<number[]>([])
const monthDays = ref<number[]>([])
const timeValue = ref<Date | null>(null)
const advancedExpression = ref('')

const weekdayLabels = ['周日', '周一', '周二', '周三', '周四', '周五', '周六']

// 初始化时间选择器
const initTimeValue = () => {
    const date = new Date()
    date.setHours(hour.value)
    date.setMinutes(minute.value)
    date.setSeconds(0)
    date.setMilliseconds(0)
    timeValue.value = date
}

// 处理时间选择变化
const handleTimeChange = (val: Date | null) => {
    if (val) {
        hour.value = val.getHours()
        minute.value = val.getMinutes()
    }
}

// 模式切换
const handleModeChange = (val: boolean | string | number) => {
    if (val) {
        mode.value = 'advanced'
        advancedExpression.value = generatedExpression.value
    } else {
        mode.value = 'simple'
        parseExpression(advancedExpression.value)
    }
}

// 频率变化重置参数
const handleFrequencyChange = () => {
    if (frequency.value === 'day' || frequency.value === 'week' || frequency.value === 'month') {
        initTimeValue()
    }
}

// 高级模式输入
const handleAdvancedInput = (val: string) => {
    emit('update:modelValue', val.trim())
}

// 生成 Cron 表达式
const generatedExpression = computed(() => {
    switch (frequency.value) {
        case 'minute':
            return '* * * * *'
        case 'everyNMinute':
            return `*/${interval.value} * * * *`
        case 'hour':
            return `${minute.value} * * * *`
        case 'everyNHour':
            return `${minute.value} */${interval.value} * * *`
        case 'day':
            return `${minute.value} ${hour.value} * * *`
        case 'week':
            return weekdays.value.length > 0
                ? `${minute.value} ${hour.value} * * ${[...weekdays.value].sort((a, b) => a - b).join(',')}`
                : `${minute.value} ${hour.value} * * *`
        case 'month':
            return monthDays.value.length > 0
                ? `${minute.value} ${hour.value} ${[...monthDays.value].sort((a, b) => a - b).join(',')} * *`
                : `${minute.value} ${hour.value} * * *`
        default:
            return '* * * * *'
    }
})

// 下次运行描述
const nextRunDescription = computed(() => {
    const pad = (n: number) => String(n).padStart(2, '0')
    const timeStr = `${pad(hour.value)}:${pad(minute.value)}`

    switch (frequency.value) {
        case 'minute':
            return '每分钟执行'
        case 'everyNMinute':
            return `每 ${interval.value} 分钟执行`
        case 'hour':
            return `每小时第 ${minute.value} 分执行`
        case 'everyNHour':
            return `每 ${interval.value} 小时，第 ${minute.value} 分执行`
        case 'day':
            return `每天 ${timeStr} 执行`
        case 'week': {
            if (weekdays.value.length === 0) return '请选择星期'
            const dayNames = [...weekdays.value]
                .sort((a, b) => a - b)
                .map((d) => weekdayLabels[d])
                .join('、')
            return `每${dayNames} ${timeStr} 执行`
        }
        case 'month': {
            if (monthDays.value.length === 0) return '请选择日期'
            const days = [...monthDays.value].sort((a, b) => a - b).join('、')
            return `每月 ${days} 日 ${timeStr} 执行`
        }
        default:
            return ''
    }
})

// 解析 Cron 表达式
const parseExpression = (expr: string) => {
    if (!expr || !expr.trim()) return

    const parts = expr.trim().split(/\s+/)
    if (parts.length !== 5) {
        // 无法识别，切换到高级模式
        mode.value = 'advanced'
        isAdvanced.value = true
        advancedExpression.value = expr
        return
    }

    const [mPart, hPart, dPart, monPart, wPart] = parts

    // * * * * * → minute
    if (mPart === '*' && hPart === '*' && dPart === '*' && monPart === '*' && wPart === '*') {
        frequency.value = 'minute'
        return
    }

    // */N * * * * → everyNMinute
    const everyNMinMatch = mPart.match(/^\*\/(\d+)$/)
    if (everyNMinMatch && hPart === '*' && dPart === '*' && monPart === '*' && wPart === '*') {
        frequency.value = 'everyNMinute'
        interval.value = parseInt(everyNMinMatch[1])
        return
    }

    // M * * * * → hour
    const minuteOnly = mPart.match(/^(\d+)$/)
    if (minuteOnly && hPart === '*' && dPart === '*' && monPart === '*' && wPart === '*') {
        frequency.value = 'hour'
        minute.value = parseInt(minuteOnly[1])
        return
    }

    // M */N * * * → everyNHour
    const everyNHourMatch = hPart.match(/^\*\/(\d+)$/)
    if (minuteOnly && everyNHourMatch && dPart === '*' && monPart === '*' && wPart === '*') {
        frequency.value = 'everyNHour'
        minute.value = parseInt(mPart)
        interval.value = parseInt(everyNHourMatch[1])
        return
    }

    // M H * * W → week
    const hourMatch = hPart.match(/^(\d+)$/)
    if (minuteOnly && hourMatch && dPart === '*' && monPart === '*' && wPart !== '*') {
        const weekMatch = wPart.match(/^[\d,]+$/)
        if (weekMatch) {
            frequency.value = 'week'
            minute.value = parseInt(mPart)
            hour.value = parseInt(hPart)
            weekdays.value = wPart.split(',').map(Number)
            initTimeValue()
            return
        }
    }

    // M H D * * → month
    if (minuteOnly && hourMatch && dPart !== '*' && monPart === '*' && wPart === '*') {
        const dayMatch = dPart.match(/^[\d,]+$/)
        if (dayMatch) {
            frequency.value = 'month'
            minute.value = parseInt(mPart)
            hour.value = parseInt(hPart)
            monthDays.value = dPart.split(',').map(Number)
            initTimeValue()
            return
        }
    }

    // M H * * * → day
    if (minuteOnly && hourMatch && dPart === '*' && monPart === '*' && wPart === '*') {
        frequency.value = 'day'
        minute.value = parseInt(mPart)
        hour.value = parseInt(hPart)
        initTimeValue()
        return
    }

    // 无法识别，切换到高级模式
    mode.value = 'advanced'
    isAdvanced.value = true
    advancedExpression.value = expr
}

// 监听简单模式下生成的表达式变化，同步给外部
watch(generatedExpression, (val) => {
    if (mode.value === 'simple') {
        emit('update:modelValue', val)
    }
})

// 监听外部传入值的变化（编辑模式）
watch(
    () => props.modelValue,
    (val) => {
        if (mode.value === 'simple' && val === generatedExpression.value) return
        if (mode.value === 'advanced' && val === advancedExpression.value) return
        parseExpression(val)
    },
    { immediate: true }
)
</script>

<style lang="scss" scoped>
.cron-builder {
    width: 100%;

    &__header {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        margin-bottom: 12px;
    }

    &__label {
        font-size: 13px;
        color: var(--el-text-color-secondary);
    }

    &__simple {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    &__params {
        min-height: 32px;
    }

    &__param-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;

        > span {
            font-size: 13px;
            color: var(--el-text-color-regular);
            white-space: nowrap;
        }
    }

    &__param-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    &__checkbox-group {
        :deep(.el-checkbox) {
            margin-right: 12px;
        }

        &--days {
            :deep(.el-checkbox) {
                margin-right: 4px;
                margin-bottom: 4px;
            }
        }
    }

    &__preview {
        background-color: var(--el-fill-color-light);
        border-radius: 4px;
        padding: 10px 12px;
        font-size: 13px;
        line-height: 1.8;
    }

    &__preview-expression {
        code {
            font-family: 'Courier New', Courier, monospace;
            color: var(--el-color-primary);
            background-color: var(--el-fill-color);
            padding: 2px 6px;
            border-radius: 3px;
        }
    }

    &__preview-label {
        color: var(--el-text-color-secondary);
    }

    &__preview-desc {
        color: var(--el-text-color-regular);
    }

    &__advanced {
        width: 100%;
    }
}
</style>
