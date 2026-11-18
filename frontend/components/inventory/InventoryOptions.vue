<script setup lang="ts">
const props = defineProps<{ modelValue?: string; disabled?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [string] }>()
const root = ref<HTMLElement>()
const rows = ref<{ name: string; values: string }[]>(
    Object.entries(JSON.parse(props.modelValue || '{}')).map(([name, values]) => ({
        name,
        values: (values as string[]).join(', '),
    })),
)
function update() {
    emit(
        'update:modelValue',
        JSON.stringify(
            Object.fromEntries(
                rows.value
                    .filter((row) => row.name.trim())
                    .map((row) => [
                        row.name.trim(),
                        row.values
                            .split(',')
                            .map((value) => value.trim())
                            .filter(Boolean),
                    ]),
            ),
        ),
    )
    nextTick(() =>
        root.value
            ?.querySelectorAll<HTMLInputElement>('[data-option-name]')
            .forEach((input, index) =>
                input.setCustomValidity(
                    rows.value.some((row, i) => i !== index && row.name.trim() === rows.value[index]?.name.trim())
                        ? 'Option names must be unique.'
                        : '',
                ),
            ),
    )
}
function removeOption(index: number) {
    rows.value.splice(index, 1)
    update()
}
</script>
<template>
    <div ref="root" class="options-editor">
        <p class="muted">Define choices such as color or size, then create a variant for each combination.</p>
        <fieldset v-for="(row, index) in rows" :key="index" class="line-card" :disabled="disabled">
            <legend>Option {{ index + 1 }}</legend>
            <div class="form-grid">
                <label :for="`option-name-${index}`"
                    >Option name<input
                        :id="`option-name-${index}`"
                        v-model="row.name"
                        data-option-name
                        required
                        placeholder="Color"
                        @input="update" /></label
                ><label :for="`option-values-${index}`"
                    >Choices (comma-separated)<input
                        :id="`option-values-${index}`"
                        v-model="row.values"
                        required
                        placeholder="Red, blue, green"
                        @input="update"
                /></label>
            </div>
            <button class="subtle danger" type="button" @click="removeOption(index)">Remove option</button>
        </fieldset>
        <button type="button" :disabled="disabled" @click="rows.push({ name: '', values: '' })">+ Add option</button>
    </div>
</template>
