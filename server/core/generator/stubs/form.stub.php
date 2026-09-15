<?php
/**
 * 表单弹窗模板 → admin/src/views/{module}/{kebab}/components/{Model}Form.vue，基于 useFormDialog()。
 *
 * @var string $module
 * @var string $model
 * @var string $modelKebab
 * @var string $tableCommentJs 表说明，已按 Vue 单引号字符串字面量的落点转义（见 ModuleBlueprint::vars()）
 * @var list<\core\generator\ColumnDescriptor> $formColumns
 */

$modelCamel = lcfirst($model);

$label = static function (\core\generator\ColumnDescriptor $column): string {
    return $column->comment !== '' ? $column->comment : $column->name;
};

$tsEnumType = static function (array $values): string {
    if ($values === []) {
        return 'string';
    }

    return implode(' | ', array_map(
        static fn (string $v): string => "'" . addslashes($v) . "'",
        $values
    ));
};

$tsType = static function (\core\generator\ColumnDescriptor $column, bool $forForm) use ($tsEnumType): string {
    return match ($column->type) {
        'string', 'text' => 'string',
        'integer' => 'number',
        'decimal' => $forForm ? 'number' : 'string',
        'boolean' => 'boolean',
        'date', 'datetime' => 'string',
        'enum' => $tsEnumType($column->enumValues),
        // json 列在表单状态里是 JSON 文本（打开时解码、提交前编码，见下方 JSON 辅助代码）；行数据里才是对象
        'json' => $forForm ? 'string' : 'Record<string, any>',
        default => 'string',
    };
};

$isRequiredInForm = static function (\core\generator\ColumnDescriptor $column): bool {
    return !$column->nullable && $column->default === null;
};

$extractLength = static function (string $rawType): ?int {
    return preg_match('/\((\d+)\)/', $rawType, $m) === 1 ? (int) $m[1] : null;
};

$defaultLiteral = static function (\core\generator\ColumnDescriptor $column): string {
    if ($column->formType === 'switch') {
        if ($column->type === 'boolean') {
            return ($column->default === '1' || $column->default === 1) ? 'true' : 'false';
        }

        return $column->default !== null ? (string) (int) $column->default : '1';
    }

    if ($column->formType === 'number') {
        if ($column->default !== null && is_numeric($column->default)) {
            return $column->type === 'decimal' ? (string) (float) $column->default : (string) (int) $column->default;
        }

        return '0';
    }

    // input / textarea / select / datepicker / image
    if ($column->default !== null && $column->type !== 'date' && $column->type !== 'datetime') {
        return "'" . addslashes((string) $column->default) . "'";
    }

    return "''";
};

$triggerFor = static function (string $formType): string {
    return in_array($formType, ['select', 'datepicker', 'image'], true) ? 'change' : 'blur';
};

$hasImage = false;
foreach ($formColumns as $c) {
    if ($c->formType === 'image') {
        $hasImage = true;
        break;
    }
}

// json 列：后端规则是 nullable|array（PHP 数组），表单里用 textarea 编辑 JSON 文本，
// 打开编辑时把对象解码成文本、提交前再编码回对象（与 admin/src/views/system/config 页的约定一致）。
$jsonColumns = array_values(array_filter($formColumns, static fn ($c): bool => $c->type === 'json'));
$hasJson = $jsonColumns !== [];

// ---- 每个字段一个 el-form-item，控件按 formType 七选一（json 列单独一支）----
$itemBlocks = [];
foreach ($formColumns as $column) {
    $colLabel = $label($column);
    $name = $column->name;
    if ($column->type === 'json') {
        $itemBlocks[] = "            <el-form-item label=\"{$colLabel}\" prop=\"{$name}\">\n"
            . "                <el-input v-model=\"form.{$name}\" type=\"textarea\" :rows=\"6\" placeholder=\"请输入{$colLabel}（JSON 格式）\" />\n"
            . "            </el-form-item>";
        continue;
    }

    $itemBlocks[] = match ($column->formType) {
        'textarea' => "            <el-form-item label=\"{$colLabel}\" prop=\"{$name}\">\n"
            . "                <el-input v-model=\"form.{$name}\" type=\"textarea\" :rows=\"4\" placeholder=\"请输入{$colLabel}\" />\n"
            . "            </el-form-item>",
        'number' => (function () use ($column, $colLabel, $name): string {
            $extra = $column->type === 'decimal' ? "\n                    :precision=\"2\"\n                    :step=\"0.01\"" : '';
            return "            <el-form-item label=\"{$colLabel}\" prop=\"{$name}\">\n"
                . "                <el-input-number\n"
                . "                    v-model=\"form.{$name}\"\n"
                . "                    :min=\"0\"{$extra}\n"
                . "                    style=\"width: 100%\"\n"
                . "                />\n"
                . "            </el-form-item>";
        })(),
        'switch' => $column->type === 'boolean'
            ? "            <el-form-item label=\"{$colLabel}\" prop=\"{$name}\">\n"
                . "                <el-switch v-model=\"form.{$name}\" />\n"
                . "            </el-form-item>"
            : "            <el-form-item label=\"{$colLabel}\" prop=\"{$name}\">\n"
                . "                <el-switch v-model=\"form.{$name}\" :active-value=\"1\" :inactive-value=\"0\" />\n"
                . "            </el-form-item>",
        'select' => (function () use ($column, $colLabel, $name): string {
            $options = implode("\n", array_map(
                static fn (string $v): string => "                    <el-option label=\"{$v}\" value=\"{$v}\" />",
                $column->enumValues
            ));
            return "            <el-form-item label=\"{$colLabel}\" prop=\"{$name}\">\n"
                . "                <el-select v-model=\"form.{$name}\" placeholder=\"请选择{$colLabel}\" clearable style=\"width: 100%\">\n"
                . "{$options}\n"
                . "                </el-select>\n"
                . "            </el-form-item>";
        })(),
        'datepicker' => (function () use ($column, $colLabel, $name): string {
            $isDatetime = $column->type === 'datetime';
            $type = $isDatetime ? 'datetime' : 'date';
            $format = $isDatetime ? 'YYYY-MM-DD HH:mm:ss' : 'YYYY-MM-DD';
            return "            <el-form-item label=\"{$colLabel}\" prop=\"{$name}\">\n"
                . "                <el-date-picker\n"
                . "                    v-model=\"form.{$name}\"\n"
                . "                    type=\"{$type}\"\n"
                . "                    value-format=\"{$format}\"\n"
                . "                    placeholder=\"请选择{$colLabel}\"\n"
                . "                    style=\"width: 100%\"\n"
                . "                />\n"
                . "            </el-form-item>";
        })(),
        'image' => "            <el-form-item label=\"{$colLabel}\" prop=\"{$name}\">\n"
            . "                <div class=\"image-uploader\">\n"
            . "                    <el-upload\n"
            . "                        :show-file-list=\"false\"\n"
            . "                        action=\"/adminapi/upload/image\"\n"
            . "                        :headers=\"uploadHeaders\"\n"
            . "                        :on-success=\"(response: any) => handleUploadSuccess(response, '{$name}')\"\n"
            . "                        :before-upload=\"beforeImageUpload\"\n"
            . "                    >\n"
            . "                        <img v-if=\"form.{$name}\" :src=\"appStore.getImageUrl(form.{$name})\" alt=\"{$colLabel}\" />\n"
            . "                        <el-icon v-else class=\"uploader-icon\"><Plus /></el-icon>\n"
            . "                    </el-upload>\n"
            . "                </div>\n"
            . "            </el-form-item>",
        default => (function () use ($column, $colLabel, $name, $extractLength): string {
            $maxlength = $extractLength($column->rawType);
            $attr = $maxlength !== null ? " maxlength=\"{$maxlength}\"" : '';
            return "            <el-form-item label=\"{$colLabel}\" prop=\"{$name}\">\n"
                . "                <el-input v-model=\"form.{$name}\" placeholder=\"请输入{$colLabel}\"{$attr} clearable />\n"
                . "            </el-form-item>";
        })(),
    };
}
$itemsBlock = implode("\n\n", $itemBlocks);

// ---- 接口字段 / defaultForm / rules ----
$fieldLines = ['    id?: number'];
$defaultLines = ['            id: undefined'];
$requiredRules = [];
foreach ($formColumns as $column) {
    $optional = $isRequiredInForm($column) ? '' : '?';
    $fieldLines[] = "    {$column->name}{$optional}: " . $tsType($column, true);
    $defaultLines[] = "            {$column->name}: " . $defaultLiteral($column);

    $ruleItems = [];
    if ($isRequiredInForm($column) && $column->formType !== 'switch') {
        $ruleItems[] = "{ required: true, message: t('message.required'), trigger: '"
            . $triggerFor($column->formType) . "' }";
    }
    if ($column->type === 'json') {
        // 前端先校验 JSON 合法且是对象/数组，避免提交后才撞后端 array 规则的 422
        $ruleItems[] = "{ validator: validateJsonField, trigger: 'blur' }";
    }
    if ($ruleItems !== []) {
        $requiredRules[] = "    {$column->name}: [" . implode(', ', $ruleItems) . ']';
    }
}
$fieldsBlock = implode("\n", $fieldLines);
$defaultFormBlock = implode(",\n", $defaultLines);
$rulesBlock = implode(",\n", $requiredRules);

$imageImports = $hasImage
    ? "import { Plus } from '@element-plus/icons-vue'\n"
    : '';
$elMessageImport = $hasImage ? "import { ElMessage } from 'element-plus'\n" : '';
$storeImportLine = $hasImage ? "import { useAppStore } from '@/store'\nimport { getToken } from '@/utils/auth'\n" : '';
$storeInitLine = $hasImage ? "const appStore = useAppStore()\n" : '';

$imageHelpers = $hasImage
    ? "\n\nconst uploadHeaders = computed(() => ({\n"
        . "    Authorization: `Bearer \${getToken()}`\n"
        . "}))\n\n"
        . "function handleUploadSuccess(response: any, field: string) {\n"
        . "    if (response.code === 200 || response.code === 0) {\n"
        . "        ;(form as Record<string, any>)[field] = response.data?.url || response.data?.path || response.data\n"
        . "    } else {\n"
        . "        ElMessage.error(response.message || t('message.uploadFailed'))\n"
        . "    }\n"
        . "}\n\n"
        . "function beforeImageUpload(file: File) {\n"
        . "    const isImage = file.type.startsWith('image/')\n"
        . "    if (!isImage) {\n"
        . "        ElMessage.error(t('message.imageOnly'))\n"
        . "        return false\n"
        . "    }\n"
        . "    const isLt2M = file.size / 1024 / 1024 < 2\n"
        . "    if (!isLt2M) {\n"
        . "        ElMessage.error(t('message.fileSizeLimit'))\n"
        . "        return false\n"
        . "    }\n"
        . "    return true\n"
        . "}"
    : '';

$jsonFieldList = implode(', ', array_map(static fn ($c): string => "'{$c->name}'", $jsonColumns));
$jsonHelpers = $hasJson
    ? "\n\n/** 表中的 json 列：表单里以 JSON 文本编辑，打开编辑时解码、提交前编码回对象 */\n"
        . "const JSON_FIELDS = [{$jsonFieldList}] as const\n\n"
        . "function decodeJsonFields(row: Record<string, any>): Record<string, any> {\n"
        . "    const data: Record<string, any> = { ...row }\n"
        . "    for (const field of JSON_FIELDS) {\n"
        . "        const value = data[field]\n"
        . "        if (value === null || value === undefined) {\n"
        . "            data[field] = ''\n"
        . "        } else if (typeof value !== 'string') {\n"
        . "            data[field] = JSON.stringify(value, null, 2)\n"
        . "        }\n"
        . "    }\n"
        . "    return data\n"
        . "}\n\n"
        . "function encodeJsonFields<T extends Record<string, any>>(data: T): T {\n"
        . "    const payload: Record<string, any> = { ...data }\n"
        . "    for (const field of JSON_FIELDS) {\n"
        . "        const text = typeof payload[field] === 'string' ? payload[field].trim() : ''\n"
        . "        payload[field] = text === '' ? null : JSON.parse(text)\n"
        . "    }\n"
        . "    return payload as T\n"
        . "}\n\n"
        . "function validateJsonField(_rule: unknown, value: unknown, callback: (error?: Error) => void) {\n"
        . "    const text = typeof value === 'string' ? value.trim() : ''\n"
        . "    if (text === '') {\n"
        . "        callback()\n"
        . "        return\n"
        . "    }\n"
        . "    try {\n"
        . "        const parsed = JSON.parse(text)\n"
        . "        callback(parsed !== null && typeof parsed === 'object' ? undefined : new Error('请输入 JSON 对象或数组'))\n"
        . "    } catch {\n"
        . "        callback(new Error('JSON 格式不正确'))\n"
        . "    }\n"
        . "}"
    : '';

$sourceData = $hasJson
    ? "decodeJsonFields(props.formData) as Partial<{$model}FormData>"
    : "props.formData as Partial<{$model}FormData>";
$createData = $hasJson ? 'encodeJsonFields(data)' : 'data';

$imageStyle = $hasImage
    ? "\n\n<style lang=\"scss\" scoped>\n"
        . ".image-uploader {\n"
        . "    :deep(.el-upload) {\n"
        . "        border: 1px dashed var(--color-border);\n"
        . "        border-radius: 6px;\n"
        . "        cursor: pointer;\n"
        . "        overflow: hidden;\n"
        . "        width: 120px;\n"
        . "        height: 120px;\n"
        . "        display: flex;\n"
        . "        align-items: center;\n"
        . "        justify-content: center;\n\n"
        . "        &:hover {\n"
        . "            border-color: var(--el-color-primary);\n"
        . "        }\n"
        . "    }\n\n"
        . "    img {\n"
        . "        width: 120px;\n"
        . "        height: 120px;\n"
        . "        object-fit: cover;\n"
        . "        display: block;\n"
        . "    }\n\n"
        . "    .uploader-icon {\n"
        . "        font-size: 24px;\n"
        . "        color: var(--color-text-disabled);\n"
        . "    }\n"
        . "}\n"
        . "</style>"
    : '';

$template = <<<'VUE'
<!-- 由代码生成器生成，可按需修改。 -->
<template>
    <el-dialog
        v-model="visible"
        :title="form.id ? '编辑__TABLE_COMMENT__' : '新增__TABLE_COMMENT__'"
        width="600px"
        destroy-on-close
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
__ITEMS_BLOCK__
        </el-form>

        <template #footer>
            <el-button @click="handleClose">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="submitting" @click="handleSubmit">{{
                $t('common.confirm')
            }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
__IMAGE_IMPORT__import type { FormRules } from 'element-plus'
__EL_MESSAGE_IMPORT__import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { __MODEL_CAMEL__Api } from '@/api/__MODEL_KEBAB__'
import { useFormDialog } from '@/hooks/useFormDialog'
__STORE_IMPORT__
const { t } = useI18n()
__STORE_INIT__
interface __MODEL__FormData {
__FIELDS_BLOCK__
}

interface Props {
    modelValue: boolean
    formData: Record<string, any>
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()__JSON_HELPERS__

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<__MODEL__FormData>({
        defaultForm: {
__DEFAULT_FORM_BLOCK__
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => __MODEL_CAMEL__Api.create(__SUBMIT_DATA__),
        updateFn: (id, data) => __MODEL_CAMEL__Api.update(id, __SUBMIT_DATA__),
        sourceData: () => __SOURCE_DATA__
    })

const rules = computed<FormRules>(() => ({
__RULES_BLOCK__
}))__IMAGE_HELPERS__
</script>__IMAGE_STYLE__
VUE;

$replacements = [
    // 落点是 Vue 单引号字符串字面量（:title="form.id ? '编辑X' : '新增X'"），必须用 JS 转义过的那个变量
    '__TABLE_COMMENT__' => $tableCommentJs,
    '__ITEMS_BLOCK__' => $itemsBlock,
    '__IMAGE_IMPORT__' => $imageImports,
    '__EL_MESSAGE_IMPORT__' => $elMessageImport,
    '__MODEL_CAMEL__' => $modelCamel,
    '__MODEL_KEBAB__' => $modelKebab,
    '__STORE_IMPORT__' => $storeImportLine,
    '__STORE_INIT__' => $storeInitLine,
    '__MODEL__' => $model,
    '__FIELDS_BLOCK__' => $fieldsBlock,
    '__DEFAULT_FORM_BLOCK__' => $defaultFormBlock,
    '__RULES_BLOCK__' => $rulesBlock,
    '__IMAGE_HELPERS__' => $imageHelpers,
    '__JSON_HELPERS__' => $jsonHelpers,
    '__SUBMIT_DATA__' => $createData,
    '__SOURCE_DATA__' => $sourceData,
    '__IMAGE_STYLE__' => $imageStyle,
];

$__out = strtr($template, $replacements);
$__out = preg_replace('/\n{3,}/', "\n\n", $__out);
?>
<?= $__out ?>

