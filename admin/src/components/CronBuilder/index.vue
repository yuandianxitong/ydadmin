<template>
    <div class="cron-builder">
        <!-- 频率 + 内联参数（一行） -->
        <div class="cron-builder__row">
            <el-select
                v-model="frequency"
                class="cron-builder__freq"
                @change="handleFrequencyChange"
            >
                <el-option label="每分钟" value="minute" />
                <el-option label="每 N 分钟" value="everyNMinute" />
                <el-option label="每小时" value="hour" />
                <el-option label="每 N 小时" value="everyNHour" />
                <el-option label="每天" value="day" />
                <el-option label="每周" value="week" />
                <el-option label="每月" value="month" />
            </el-select>

            <!-- 每 N 分钟 -->
            <template v-if="frequency === 'everyNMinute'">
                <span class="cron-builder__text">每</span>
                <el-input-number
                    v-model="interval"
                    :min="1"
                    :max="59"
                    size="default"
                    controls-position="right"
                    class="cron-builder__num"
                />
                <span class="cron-builder__text">分钟执行一次</span>
            </template>

            <!-- 每小时 -->
            <template v-else-if="frequency === 'hour'">
                <span class="cron-builder__text">第</span>
                <el-select v-model="minute" class="cron-builder__sub-select">
                    <el-option
                        v-for="m in 60"
                        :key="m - 1"
                        :label="`${m - 1} 分`"
                        :value="m - 1"
                    />
                </el-select>
                <span class="cron-builder__text">执行</span>
            </template>

            <!-- 每 N 小时 -->
            <template v-else-if="frequency === 'everyNHour'">
                <span class="cron-builder__text">每</span>
                <el-input-number
                    v-model="interval"
                    :min="1"
                    :max="23"
                    size="default"
                    controls-position="right"
                    class="cron-builder__num"
                />
                <span class="cron-builder__text">小时，第</span>
                <el-select v-model="minute" class="cron-builder__sub-select">
                    <el-option
                        v-for="m in 60"
                        :key="m - 1"
                        :label="`${m - 1} 分`"
                        :value="m - 1"
                    />
                </el-select>
                <span class="cron-builder__text">分执行</span>
            </template>

            <!-- 每天 / 每周 / 每月：行内时间选择 -->
            <template
                v-else-if="
                    frequency === 'day' || frequency === 'week' || frequency === 'month'
                "
            >
                <span class="cron-builder__text">执行时间</span>
                <el-time-picker
                    v-model="timeValue"
                    format="HH:mm"
                    placeholder="选择时间"
                    class="cron-builder__time"
                    @change="handleTimeChange"
                />
            </template>
        </div>

        <!-- 每周 -->
        <div v-if="frequency === 'week'" class="cron-builder__panel">
            <div class="cron-builder__panel-label">选择星期</div>
            <el-checkbox-group v-model="weekdays" class="cron-builder__checkbox-group">
                <el-checkbox-button
                    v-for="(label, idx) in weekdayLabels"
                    :key="idx"
                    :value="idx"
                >
                    {{ label }}
                </el-checkbox-button>
            </el-checkbox-group>
        </div>

        <!-- 每月 -->
        <div v-if="frequency === 'month'" class="cron-builder__panel">
            <div class="cron-builder__panel-label">选择日期</div>
            <el-checkbox-group
                v-model="monthDays"
                class="cron-builder__checkbox-group cron-builder__checkbox-group--days"
            >
                <el-checkbox-button v-for="d in 31" :key="d" :value="d">
                    {{ d }}
                </el-checkbox-button>
            </el-checkbox-group>
        </div>

        <!-- 预览 -->
        <div class="cron-builder__preview">
            <div class="cron-builder__preview-row">
                <span class="cron-builder__preview-tag">表达式</span>
                <code class="cron-builder__preview-code">{{ generatedExpression }}</code>
            </div>
            <div class="cron-builder__preview-row">
                <span class="cron-builder__preview-tag">说明</span>
                <span class="cron-builder__preview-text">{{ nextRunDescription }}</span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { cronToText } from './cron-text'

type Frequency = 'minute' | 'everyNMinute' | 'hour' | 'everyNHour' | 'day' | 'week' | 'month'

const props = defineProps<{
    modelValue: string
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const frequency = ref<Frequency>('minute')
const minute = ref(0)
const hour = ref(0)
const interval = ref(5)
const weekdays = ref<number[]>([])
const monthDays = ref<number[]>([])
const timeValue = ref<Date | null>(null)

const weekdayLabels = ['周日', '周一', '周二', '周三', '周四', '周五', '周六']

const initTimeValue = () => {
    const date = new Date()
    date.setHours(hour.value)
    date.setMinutes(minute.value)
    date.setSeconds(0)
    date.setMilliseconds(0)
    timeValue.value = date
}

const handleTimeChange = (val: Date | null) => {
    if (val) {
        hour.value = val.getHours()
        minute.value = val.getMinutes()
    }
}

const handleFrequencyChange = () => {
    if (frequency.value === 'day' || frequency.value === 'week' || frequency.value === 'month') {
        initTimeValue()
    }
}

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

const nextRunDescription = computed(() => {
    if (frequency.value === 'week' && weekdays.value.length === 0) return '请选择星期'
    if (frequency.value === 'month' && monthDays.value.length === 0) return '请选择日期'
    return cronToText(generatedExpression.value) ?? ''
})

const parseExpression = (expr: string) => {
    if (!expr || !expr.trim()) return

    const parts = expr.trim().split(/\s+/)
    if (parts.length !== 5) return

    const [mPart, hPart, dPart, monPart, wPart] = parts

    if (mPart === '*' && hPart === '*' && dPart === '*' && monPart === '*' && wPart === '*') {
        frequency.value = 'minute'
        return
    }

    const everyNMinMatch = mPart.match(/^\*\/(\d+)$/)
    if (everyNMinMatch && hPart === '*' && dPart === '*' && monPart === '*' && wPart === '*') {
        frequency.value = 'everyNMinute'
        interval.value = parseInt(everyNMinMatch[1])
        return
    }

    const minuteOnly = mPart.match(/^(\d+)$/)
    if (minuteOnly && hPart === '*' && dPart === '*' && monPart === '*' && wPart === '*') {
        frequency.value = 'hour'
        minute.value = parseInt(minuteOnly[1])
        return
    }

    const everyNHourMatch = hPart.match(/^\*\/(\d+)$/)
    if (minuteOnly && everyNHourMatch && dPart === '*' && monPart === '*' && wPart === '*') {
        frequency.value = 'everyNHour'
        minute.value = parseInt(mPart)
        interval.value = parseInt(everyNHourMatch[1])
        return
    }

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

    if (minuteOnly && hourMatch && dPart === '*' && monPart === '*' && wPart === '*') {
        frequency.value = 'day'
        minute.value = parseInt(mPart)
        hour.value = parseInt(hPart)
        initTimeValue()
        return
    }
}

watch(generatedExpression, (val) => {
    emit('update:modelValue', val)
})

watch(
    () => props.modelValue,
    (val) => {
        if (val === generatedExpression.value) return
        parseExpression(val)
    },
    { immediate: true }
)
</script>

<style lang="scss" scoped>
.cron-builder {
    width: 100%;

    display: flex;
    flex-direction: column;
    gap: 12px;

    &__row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    &__freq {
        width: 140px;
        flex-shrink: 0;
    }

    &__text {
        font-size: 13px;
        color: var(--el-text-color-regular);
        white-space: nowrap;
    }

    &__num {
        width: 110px;
    }

    &__sub-select {
        width: 100px;
    }

    &__time {
        width: 140px;
    }

    &__panel {
        background-color: var(--el-fill-color-lighter);
        border: 1px solid var(--el-border-color-lighter);
        border-radius: 6px;
        padding: 12px;
    }

    &__panel-label {
        font-size: 13px;
        color: var(--el-text-color-secondary);
        margin-bottom: 10px;
    }

    &__checkbox-group {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;

        :deep(.el-checkbox-button) {
            margin: 0;
        }

        :deep(.el-checkbox-button__inner) {
            border-radius: 4px !important;
            border-left: 1px solid var(--el-border-color);
            padding: 6px 12px;
            font-size: 13px;
        }

        &--days {
            :deep(.el-checkbox-button__inner) {
                min-width: 40px;
                padding: 6px 0;
                text-align: center;
            }
        }
    }

    &__preview {
        background: linear-gradient(
            135deg,
            var(--el-color-primary-light-9) 0%,
            var(--el-fill-color-light) 100%
        );
        border: 1px solid var(--el-color-primary-light-7);
        border-radius: 6px;
        padding: 10px 14px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    &__preview-row {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        line-height: 1.6;
    }

    &__preview-tag {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 48px;
        padding: 1px 8px;
        font-size: 12px;
        color: var(--el-color-primary);
        background-color: var(--el-color-primary-light-8);
        border-radius: 3px;
    }

    &__preview-code {
        font-family: 'JetBrains Mono', 'Courier New', Courier, monospace;
        color: var(--el-color-primary);
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    &__preview-text {
        color: var(--el-text-color-regular);
    }
}
</style>
