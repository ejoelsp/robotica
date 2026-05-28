<script setup>
import { computed, ref } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import { ArrowUpTrayIcon, ArrowRightIcon, UserCircleIcon } from "@heroicons/vue/24/outline";

defineOptions({
  layout: null,
});

const page = usePage();

const form = useForm({
  photo: null,
});

const fileInput = ref(null);
const previewUrl = ref(null);

const user = computed(() => page.props.user ?? {});

const canSubmit = computed(() => {
  return !!form.photo && !form.processing;
});

const openFilePicker = () => {
  fileInput.value?.click();
};

const handleFileChange = (event) => {
  const file = event.target.files?.[0] ?? null;
  form.photo = file;

  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
  }

  if (file) {
    previewUrl.value = URL.createObjectURL(file);
  }
};

const submit = () => {
  if (!form.photo) return;

  form.post("/juez/completar-perfil", {
    forceFormData: true,
  });
};
</script>

<template>
  <div class="min-h-screen bg-[#17388d] px-3 py-4 sm:px-4 sm:py-5">
    <div class="mx-auto flex min-h-[calc(100vh-32px)] max-w-4xl flex-col items-center justify-center sm:min-h-[calc(100vh-40px)]">
      <!-- Header -->
      <div class="mb-5 flex flex-col items-center text-center">
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 shadow-lg">
          <UserCircleIcon class="h-8 w-8 text-white" />
        </div>

        <h1 class="text-2xl font-light tracking-tight text-white sm:text-4xl">
          ¡Bienvenido!
        </h1>
        <p class="mt-2 text-base text-blue-100 sm:text-xl">
          Ingresa una foto de perfil para comenzar
        </p>
      </div>

      <!-- Steps -->
      <div class="mb-5 flex items-center justify-center gap-4 sm:mb-6">
        <div class="h-[2px] w-16 bg-slate-500/60 sm:w-20"></div>
      </div>

      <!-- Card -->
      <div class="w-full max-w-2xl rounded-2xl bg-slate-100 px-4 py-6 shadow-2xl sm:rounded-[24px] sm:px-8 sm:py-8">
        <div class="text-center">
          <h2 class="text-2xl font-light text-slate-800 sm:text-4xl">
            Foto de Perfil
          </h2>
          <p class="mt-2 text-base text-slate-500 sm:mt-3 sm:text-xl">
            Esta foto aparecerá en tu perfil de juez
          </p>
        </div>

        <form class="mt-6 sm:mt-8" @submit.prevent="submit">
          <input
            ref="fileInput"
            type="file"
            accept=".jpg,.jpeg,.png,.webp"
            class="hidden"
            @change="handleFileChange"
          />

          <!-- Upload / Preview -->
          <div class="flex flex-col items-center">
            <div class="relative">
              <div
                class="flex h-36 w-36 items-center justify-center overflow-hidden rounded-full border-[5px] sm:h-48 sm:w-48"
                :class="previewUrl ? 'border-blue-600 bg-white' : 'border-dashed border-slate-300 bg-slate-200'"
              >
                <template v-if="previewUrl">
                  <img
                    :src="previewUrl"
                    alt="Vista previa"
                    class="h-full w-full object-cover"
                  />
                </template>

                <template v-else>
                  <ArrowUpTrayIcon class="h-12 w-12 text-slate-400 sm:h-16 sm:w-16" />
                </template>
              </div>

              <button
                type="button"
                @click="openFilePicker"
                class="absolute bottom-1 right-1 flex h-11 w-11 items-center justify-center rounded-full bg-blue-600 shadow-lg transition hover:bg-blue-700 sm:h-12 sm:w-12"
              >
                <ArrowUpTrayIcon class="h-6 w-6 text-white" />
              </button>
            </div>

            <p class="mt-4 text-sm text-slate-500 sm:mt-5 sm:text-lg">
              Formatos: JPG, PNG. Tamaño máximo: 5MB
            </p>

            <p
              v-if="form.errors.photo"
              class="mt-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
              {{ form.errors.photo }}
            </p>
          </div>

          <!-- User info -->
          <div class="mt-6 rounded-2xl bg-slate-200/60 px-4 py-4 sm:mt-8 sm:rounded-3xl sm:px-5 sm:py-5">
            <div>
              <p class="text-sm text-slate-500 sm:text-base">Nombre</p>
              <p class="mt-1 text-xl font-light text-slate-800 sm:text-3xl">
                {{ user.name }} {{ user.last_name }}
              </p>
            </div>

            <div class="mt-5">
              <p class="text-sm text-slate-500 sm:text-base">Correo electrónico</p>
              <p class="mt-1 break-all text-lg font-light text-slate-800 sm:text-2xl">
                {{ user.email }}
              </p>
            </div>
          </div>

          <!-- Button -->
          <button
            type="submit"
            :disabled="!canSubmit"
            class="mt-6 flex w-full items-center justify-center gap-3 rounded-2xl px-6 py-3.5 text-lg font-semibold text-white transition sm:mt-8 sm:py-4 sm:text-2xl"
            :class="
              canSubmit
                ? 'bg-blue-600 hover:bg-blue-700'
                : 'cursor-not-allowed bg-blue-300'
            "
          >
            <span>{{ form.processing ? "Guardando..." : "Continuar" }}</span>
            <ArrowRightIcon class="h-6 w-6 sm:h-7 sm:w-7" />
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
