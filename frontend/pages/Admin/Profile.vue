<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import ProfilePage from '../../components/profile/ProfilePage.vue';

defineOptions({ layout: AdminLayout });
</script>

<template>
  <ProfilePage role-label="Administrador" />
</template>
