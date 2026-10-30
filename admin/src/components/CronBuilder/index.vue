<template>
    <div class="cron-builder">
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
            <el-option label="自定义表达式" value="custom" />
        </el-select>

        <div class="cron-builder__params">
            <div v-if="frequency === 'custom'" class="cron-builder__param-row">
                <el-input
                    v-model="customExpression"
                    placeholder="分 时 日 月 周，如 */10 8-20 * * *"
                    style="width: 240px"
                />
                <span>Cron 表达式</span>
            </div>

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

            <div v-if="frequency === 'hour'" class="cron-builder__param-row">
                <span>每小时第</span>
                <el-select v-model="minute" style="width: 100px">
                    <el-option v-for="m in 60" :key="m - 1" :label="`${m - 1} 分`" :value="m - 1" />
                </el-select>
                <span>执行</span>
            </div>

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
                    <el-option v-for="m in 60" :key="m - 1" :label="`${m - 1} 分`" :value="m - 1" />
                </el-select>
                <span>执行</span>
            </div>

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
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { type CronFrequency, parseCron } from './utils'

const props = defineProps<{
    modelValue: string
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const frequency = ref<CronFrequency | 'custom'>('minute')
const customExpression = ref('')
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
    if (frequency.value === 'custom' && !customExpression.value) {
        customExpression.value = props.modelValue
    }
    if (frequency.value === 'day' || frequency.value === 'week' || frequency.value === 'month') {
        initTimeValue()
    }
    if (frequency.value === 'week' && weekdays.value.length === 0) {
        weekdays.value = [1]
    }
    if (frequency.value === 'month' && monthDays.value.length === 0) {
        monthDays.value = [1]
    }
}

const generatedExpression = computed(() => {
    switch (frequency.value) {
        case 'custom':
            return customExpression.value
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

const applyParsed = (expr: string) => {
    const parsed = parseCron(expr)

    // 可视化模式解析不了的合法表达式：进入自定义模式原样保留，绝不覆盖用户数据
    if (!parsed.supported && expr && expr.trim()) {
        frequency.value = 'custom'
        customExpression.value = expr
        return
    }

    frequency.value = parsed.frequency
    minute.value = parsed.minute
    hour.value = parsed.hour
    interval.value = parsed.interval
    weekdays.value = parsed.weekdays
    monthDays.value = parsed.monthDays
    if (parsed.frequency === 'day' || parsed.frequency === 'week' || parsed.frequency === 'month') {
        initTimeValue()
    }
    // 空值场景（新建表单）：回填默认的「每分钟」表达式
    if (!parsed.supported) {
        emit('update:modelValue', generatedExpression.value)
    }
}

watch(generatedExpression, (val) => {
    emit('update:modelValue', val)
})

watch(
    () => props.modelValue,
    (val) => {
        if (val === generatedExpression.value) return
        applyParsed(val)
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
}
</style>
