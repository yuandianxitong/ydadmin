<template>
    <div class="cron-builder">
        <!-- 简单模式 -->
        <div v-if="mode === 'simple'" class="cron-builder__simple">
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

        <!-- 模式切换（底部） -->
        <div class="cron-builder__footer">
            <el-link type="primary" :underline="false" @click="toggleMode">
                <el-icon class="cron-builder__footer-icon"><Switch /></el-icon>
                切换至{{ mode === 'simple' ? '高级' : '简单' }}模式
            </el-link>
        </div>
    </div>
</template>

<script setup lang="ts">
import { QuestionFilled, Switch } from '@element-plus/icons-vue'
import { computed, ref, watch } from 'vue'

type Frequency = 'minute' | 'everyNMinute' | 'hour' | 'everyNHour' | 'day' | 'week' | 'month'

const props = defineProps<{
    modelValue: string
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const mode = ref<'simple' | 'advanced'>('simple')
const frequency = ref<Frequency>('minute')
const minute = ref(0)
const hour = ref(0)
const interval = ref(5)
const weekdays = ref<number[]>([])
const monthDays = ref<number[]>([])
const timeValue = ref<Date | null>(null)
const advancedExpression = ref('')

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

const toggleMode = () => {
    if (mode.value === 'simple') {
        mode.value = 'advanced'
        advancedExpression.value = generatedExpression.value
    } else {
        mode.value = 'simple'
        parseExpression(advancedExpression.value)
    }
}

const handleFrequencyChange = () => {
    if (frequency.value === 'day' || frequency.value === 'week' || frequency.value === 'month') {
        initTimeValue()
    }
}

const handleAdvancedInput = (val: string) => {
    emit('update:modelValue', val.trim())
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

const parseExpression = (expr: string) => {
    if (!expr || !expr.trim()) return

    const parts = expr.trim().split(/\s+/)
    if (parts.length !== 5) {
        mode.value = 'advanced'
        advancedExpression.value = expr
        return
    }

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

    mode.value = 'advanced'
    advancedExpression.value = expr
}

watch(generatedExpression, (val) => {
    if (mode.value === 'simple') {
        emit('update:modelValue', val)
    }
})

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

    &__simple {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

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

    &__advanced {
        width: 100%;
    }

    &__footer {
        display: flex;
        justify-content: flex-end;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed var(--el-border-color-lighter);
    }

    &__footer-icon {
        margin-right: 4px;
        font-size: 14px;
    }
}
</style>
