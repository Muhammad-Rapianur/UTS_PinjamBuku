<script setup>
import { ref, onMounted } from 'vue';
import axios from './axios';

const books = ref([]);
const loading = ref(true);
const errorMessage = ref('');

const fetchBooks = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/books');
        books.value = response.data;
    } catch (error) {
        errorMessage.value = 'Gagal memuat data buku dari server.';
        console.error(error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchBooks();
});
</script>

<template>
  <div style="padding: 30px; font-family: sans-serif;">
    <h2>Daftar Buku - PinjamBuku</h2>
    
    <!-- Indikator Loading -->
    <p v-if="loading">Memuat data...</p>

    <!-- Pesan Error -->
    <p v-if="errorMessage" style="color: red;">{{ errorMessage }}</p>

    <!-- Data Kosong -->
    <p v-if="!loading && books.length === 0">Belum ada buku tersedia.</p>

    <!-- Tabel Daftar Buku -->
    <table v-if="books.length > 0" border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-top: 15px;">
      <thead>
        <tr style="background-color: #f4f4f4;">
          <th>No</th>
          <th>Judul Buku</th>
          <th>Penulis</th>
          <th>Kategori</th>
          <th>Stok</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(book, index) in books" :key="book.id">
          <td style="text-align: center;">{{ index + 1 }}</td>
          <td>{{ book.title }}</td>
          <td>{{ book.author }}</td>
          <td>{{ book.category ? book.category.name : '-' }}</td>
          <td style="text-align: center;">{{ book.stock }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>