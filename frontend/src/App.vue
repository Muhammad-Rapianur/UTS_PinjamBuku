<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const api = axios.create({
  baseURL: 'http://127.0.0.1:8000/api'
})

const books = ref([])
const borrowings = ref([])
const errorMessage = ref('')
const successMessage = ref('')
const token = ref(localStorage.getItem('token') || '')
const user = ref(JSON.parse(localStorage.getItem('user')) || null)

// State Login
const loginForm = ref({ email: '', password: '' })
const loginError = ref('')

// State Tambah Buku (Admin)
const bookForm = ref({ title: '', author: '', category_id: 1, stock: 1 })
const bookErrors = ref({})

const fetchBooks = async () => {
  try {
    errorMessage.value = ''
    const response = await api.get('/books')
    books.value = response.data
  } catch (error) {
    errorMessage.value = 'Gagal memuat data buku dari server.'
  }
}

const fetchBorrowings = async () => {
  try {
    const response = await api.get('/borrowings', {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    borrowings.value = response.data
  } catch (error) {
    console.error('Gagal memuat riwayat peminjaman')
  }
}

// Login
const login = async () => {
  loginError.value = ''
  try {
    const response = await api.post('/login', loginForm.value)
    token.value = response.data.access_token
    user.value = response.data.user
    
    localStorage.setItem('token', token.value)
    localStorage.setItem('user', JSON.stringify(user.value))
    
    successMessage.value = 'Login berhasil!'
    fetchBooks()
    fetchBorrowings()
  } catch (error) {
    if (error.response && error.response.status === 422) {
      loginError.value = 'Email atau password salah.'
    } else {
      loginError.value = 'Terjadi kesalahan pada server.'
    }
  }
}

// Logout
const logout = async () => {
  try {
    await api.post('/logout', {}, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
  } catch (e) {
    console.error(e)
  }
  token.value = ''
  user.value = null
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  successMessage.value = 'Logout berhasil.'
  books.value = []
  borrowings.value = []
}

// Admin: Tambah Buku
const addBook = async () => {
  bookErrors.value = {}
  successMessage.value = ''
  errorMessage.value = ''

  try {
    const response = await api.post('/books', bookForm.value, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    successMessage.value = response.data.message
    bookForm.value.title = ''
    bookForm.value.author = ''
    bookForm.value.stock = 1
    fetchBooks()
  } catch (error) {
    if (error.response && error.response.status === 422) {
      bookErrors.value = error.response.data.errors
    } else {
      errorMessage.value = 'Terjadi kesalahan saat menyimpan data.'
    }
  }
}

// Member: Pinjam Buku
const borrowBook = async (bookId) => {
  successMessage.value = ''
  errorMessage.value = ''
  try {
    const today = new Date().toISOString().slice(0, 10)
    const response = await api.post('/borrowings', {
      book_id: bookId,
      borrow_date: today
    }, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    successMessage.value = response.data.message
    fetchBooks()
    fetchBorrowings()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Gagal meminjam buku.'
  }
}

// Kembalikan Buku
const returnBook = async (borrowingId) => {
  successMessage.value = ''
  errorMessage.value = ''
  try {
    const response = await api.put(`/borrowings/${borrowingId}/return`, {}, {
      headers: { Authorization: `Bearer ${token.value}` }
    })
    successMessage.value = response.data.message
    fetchBooks()
    fetchBorrowings()
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Gagal mengembalikan buku.'
  }
}

onMounted(() => {
  if (token.value) {
    fetchBooks()
    fetchBorrowings()
  }
})
</script>

<template>
  <div style="padding: 30px; font-family: Arial, sans-serif; max-width: 900px; margin: auto;">
    <h2>Aplikasi PinjamBuku</h2>

    <!-- Pesan Global -->
    <div v-if="errorMessage" style="color: red; margin-bottom: 15px; font-weight: bold;">{{ errorMessage }}</div>
    <div v-if="successMessage" style="color: green; margin-bottom: 15px; font-weight: bold;">{{ successMessage }}</div>

    <!-- Halaman Login -->
    <div v-if="!token" style="background: #f0f4f8; padding: 20px; margin-bottom: 25px; border-radius: 5px; max-width: 400px;">
      <h3>Login Sistem</h3>
      <form @submit.prevent="login">
        <div style="margin-bottom: 10px;">
          <label>Email:</label><br>
          <input v-model="loginForm.email" type="email" style="width: 100%; padding: 6px;" required />
        </div>
        <div style="margin-bottom: 10px;">
          <label>Password:</label><br>
          <input v-model="loginForm.password" type="password" style="width: 100%; padding: 6px;" required />
        </div>
        <div v-if="loginError" style="color: red; margin-bottom: 10px; font-size: 13px;">{{ loginError }}</div>
        <button type="submit" style="padding: 8px 15px; background: #007bff; color: white; border: none; cursor: pointer;">Login</button>
      </form>
    </div>

    <!-- Tampilan Setelah Login -->
    <div v-else>
      <div style="background: #e6ffed; padding: 15px; margin-bottom: 25px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center;">
        <span>Login sebagai: <b>{{ user?.name }}</b> (Role: <span style="text-transform: uppercase; color: #007bff;">{{ user?.role }}</span>)</span>
        <button @click="logout" style="padding: 6px 12px; background: #dc3545; color: white; border: none; cursor: pointer;">Logout</button>
      </div>

      <!-- ==================== PANEL ADMIN: TAMBAH BUKU ==================== -->
      <div v-if="user?.role === 'admin'" style="background: #f9f9f9; padding: 15px; margin-bottom: 25px; border-radius: 5px; border-left: 5px solid #28a745;">
        <h3>Panel Admin: Tambah Buku Baru</h3>
        <form @submit.prevent="addBook">
          <div style="margin-bottom: 10px;">
            <label>Judul Buku:</label><br>
            <input v-model="bookForm.title" type="text" style="width: 100%; padding: 6px;" />
            <span v-if="bookErrors.title" style="color: red; font-size: 12px;">{{ bookErrors.title[0] }}</span>
          </div>
          <div style="margin-bottom: 10px;">
            <label>Penulis:</label><br>
            <input v-model="bookForm.author" type="text" style="width: 100%; padding: 6px;" />
            <span v-if="bookErrors.author" style="color: red; font-size: 12px;">{{ bookErrors.author[0] }}</span>
          </div>
          <div style="margin-bottom: 10px;">
            <label>Kategori ID:</label><br>
            <input v-model.number="bookForm.category_id" type="number" style="width: 100%; padding: 6px;" />
          </div>
          <div style="margin-bottom: 10px;">
            <label>Stok:</label><br>
            <input v-model.number="bookForm.stock" type="number" style="width: 100%; padding: 6px;" />
          </div>
          <button type="submit" style="padding: 8px 15px; background: #28a745; color: white; border: none; cursor: pointer;">Simpan Buku</button>
        </form>
      </div>

      <!-- ==================== DAFTAR BUKU ==================== -->
      <h3>Daftar Buku Perpustakaan</h3>
      <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
        <thead>
          <tr style="background: #f2f2f2;">
            <th>No</th>
            <th>Judul Buku</th>
            <th>Penulis</th>
            <th>Stok</th>
            <th v-if="user?.role === 'member'">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(book, index) in books" :key="book.id">
            <td align="center">{{ index + 1 }}</td>
            <td>{{ book.title }}</td>
            <td>{{ book.author }}</td>
            <td align="center">{{ book.stock }}</td>
            <td align="center" v-if="user?.role === 'member'">
              <button 
                @click="borrowBook(book.id)" 
                :disabled="book.stock < 1"
                :style="{ background: book.stock < 1 ? '#ccc' : '#007bff', color: 'white', border: 'none', padding: '5px 10px', cursor: book.stock < 1 ? 'not-allowed' : 'pointer' }">
                {{ book.stock < 1 ? 'Habis' : 'Pinjam' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- ==================== RIWAYAT PEMINJAMAN ==================== -->
      <h3>{{ user?.role === 'admin' ? 'Semua Riwayat Peminjaman (Admin)' : 'Riwayat Peminjaman Saya' }}</h3>
      <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
        <thead>
          <tr style="background: #f2f2f2;">
            <th>No</th>
            <th v-if="user?.role === 'admin'">Nama Peminjam</th>
            <th>Judul Buku</th>
            <th>Tanggal Pinjam</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(borrow, index) in borrowings" :key="borrow.id">
            <!-- Jika Admin, tampilkan semua. Jika Member, hanya tampilkan milik member tersebut -->
            <tr v-if="user?.role === 'admin' || borrow.user_id === user?.id">
              <td align="center">{{ index + 1 }}</td>
              <td v-if="user?.role === 'admin'">{{ borrow.user?.name }}</td>
              <td>{{ borrow.book?.title }}</td>
              <td align="center">{{ borrow.borrow_date }}</td>
              <td align="center">
                <span :style="{ color: borrow.status === 'dipinjam' ? 'orange' : 'green', fontWeight: 'bold' }">
                  {{ borrow.status }}
                </span>
              </td>
              <td align="center">
                <button 
                  v-if="borrow.status === 'dipinjam'"
                  @click="returnBook(borrow.id)"
                  style="background: #ffc107; color: black; border: none; padding: 5px 10px; cursor: pointer; font-size: 12px;">
                  Kembalikan
                </button>
                <span v-else style="color: gray; font-size: 12px;">Selesai</span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>