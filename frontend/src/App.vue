<script setup>
import { ref, computed, onMounted } from 'vue'
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

// State Search & Filter Buku
const searchBook = ref('')
const filterStockStatus = ref('all') // 'all', 'available', 'empty'
const currentBookPage = ref(1)
const itemsPerBookPage = 10

// State Search & Filter Riwayat Peminjaman
const searchBorrowing = ref('')
const filterBorrowStatus = ref('all') // 'all', 'dipinjam', 'dikembalikan'
const currentBorrowPage = ref(1)
const itemsPerBorrowPage = 10

// Toggle tampilan antara Login dan Register
const isRegistering = ref(false)

// State Login
const loginForm = ref({ email: '', password: '' })
const loginError = ref('')

// State Register
const registerForm = ref({ name: '', email: '', password: '' })
const registerError = ref('')

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

// Register (Daftar Akun Baru)
const register = async () => {
  registerError.value = ''
  try {
    const response = await api.post('/register', registerForm.value)
    token.value = response.data.access_token
    user.value = response.data.user
    
    localStorage.setItem('token', token.value)
    localStorage.setItem('user', JSON.stringify(user.value))
    
    successMessage.value = 'Registrasi berhasil! Selamat datang.'
    fetchBooks()
    fetchBorrowings()
  } catch (error) {
    if (error.response && error.response.status === 422) {
      registerError.value = 'Format data salah atau email sudah terdaftar.'
    } else {
      registerError.value = 'Terjadi kesalahan pada server saat registrasi.'
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

// --- COMPUTED PROPERTIES UNTUK SEARCH, FILTER, & PAGINATION ---

const filteredBooks = computed(() => {
  return books.value.filter(book => {
    const keyword = searchBook.value.toLowerCase()
    const matchesKeyword = (book.title?.toLowerCase().includes(keyword) || false) || 
                           (book.author?.toLowerCase().includes(keyword) || false)
    
    if (filterStockStatus.value === 'available') {
      return matchesKeyword && Number(book.stock) > 0
    } else if (filterStockStatus.value === 'empty') {
      return matchesKeyword && Number(book.stock) <= 0
    }
    return matchesKeyword
  })
})

// Pagination Buku
const totalBookPages = computed(() => Math.ceil(filteredBooks.value.length / itemsPerBookPage) || 1)
const paginatedBooks = computed(() => {
  const start = (currentBookPage.value - 1) * itemsPerBookPage
  const end = start + itemsPerBookPage
  return filteredBooks.value.slice(start, end)
})

const filteredBorrowings = computed(() => {
  return borrowings.value.filter(borrow => {
    // Validasi kepemilikan data (Admin melihat semua, Member melihat milik sendiri)
    const isOwnerOrAdmin = (user.value?.role === 'admin') || (borrow.user_id === user.value?.id)
    if (!isOwnerOrAdmin) return false

    const keyword = searchBorrowing.value.toLowerCase()
    const matchBook = borrow.book?.title?.toLowerCase().includes(keyword) || false
    const matchUser = borrow.user?.name?.toLowerCase().includes(keyword) || false
    const matchesKeyword = matchBook || matchUser

    if (filterBorrowStatus.value !== 'all') {
      return matchesKeyword && borrow.status === filterBorrowStatus.value
    }

    return matchesKeyword
  })
})

// Pagination Riwayat Peminjaman
const totalBorrowPages = computed(() => Math.ceil(filteredBorrowings.value.length / itemsPerBorrowPage) || 1)
const paginatedBorrowings = computed(() => {
  const start = (currentBorrowPage.value - 1) * itemsPerBorrowPage
  const end = start + itemsPerBorrowPage
  return filteredBorrowings.value.slice(start, end)
})

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

    <!-- Halaman Login / Register (Jika belum login) -->
    <div v-if="!token" style="background: #f0f4f8; padding: 20px; margin-bottom: 25px; border-radius: 5px; max-width: 400px;">
      
      <!-- FORM LOGIN -->
      <div v-if="!isRegistering">
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
        <p style="margin-top: 15px; font-size: 13px;">
          Belum punya akun? <a href="#" @click.prevent="isRegistering = true" style="color: #007bff;">Daftar di sini</a>
        </p>
      </div>

      <!-- FORM REGISTER (PENDAFTARAN) -->
      <div v-else>
        <h3>Daftar Akun Member Baru</h3>
        <form @submit.prevent="register">
          <div style="margin-bottom: 10px;">
            <label>Nama Lengkap:</label><br>
            <input v-model="registerForm.name" type="text" style="width: 100%; padding: 6px;" required />
          </div>
          <div style="margin-bottom: 10px;">
            <label>Email:</label><br>
            <input v-model="registerForm.email" type="email" style="width: 100%; padding: 6px;" required />
          </div>
          <div style="margin-bottom: 10px;">
            <label>Password:</label><br>
            <input v-model="registerForm.password" type="password" style="width: 100%; padding: 6px;" required />
          </div>
          <div v-if="registerError" style="color: red; margin-bottom: 10px; font-size: 13px;">{{ registerError }}</div>
          <button type="submit" style="padding: 8px 15px; background: #28a745; color: white; border: none; cursor: pointer;">Daftar & Masuk</button>
        </form>
        <p style="margin-top: 15px; font-size: 13px;">
          Sudah punya akun? <a href="#" @click.prevent="isRegistering = false" style="color: #007bff;">Login di sini</a>
        </p>
      </div>

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
      
      <!-- Kontrol Pencarian & Filter Buku -->
      <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
        <input 
          v-model="searchBook" 
          @input="currentBookPage = 1"
          type="text" 
          placeholder="Cari judul buku atau penulis..." 
          style="flex: 1; min-width: 200px; padding: 6px;" 
        />
        <select v-model="filterStockStatus" @change="currentBookPage = 1" style="padding: 6px;">
          <option value="all">Semua Status Stok</option>
          <option value="available">Tersedia (Stok > 0)</option>
          <option value="empty">Habis (Stok 0)</option>
        </select>
      </div>

      <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
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
          <tr v-if="paginatedBooks.length === 0">
            <td :colspan="user?.role === 'member' ? 5 : 4" align="center" style="color: gray;">Tidak ada buku yang ditemukan.</td>
          </tr>
          <tr v-for="(book, index) in paginatedBooks" :key="book.id">
            <td align="center">{{ (currentBookPage - 1) * itemsPerBookPage + index + 1 }}</td>
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

      <!-- Navigasi Pagination Buku -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; font-size: 14px;">
        <span>Halaman {{ currentBookPage }} dari {{ totalBookPages }}</span>
        <div>
          <button 
            @click="currentBookPage--" 
            :disabled="currentBookPage === 1"
            style="padding: 5px 10px; margin-right: 5px; cursor: pointer;">Sebelumnya</button>
          <button 
            @click="currentBookPage++" 
            :disabled="currentBookPage >= totalBookPages"
            style="padding: 5px 10px; cursor: pointer;">Selanjutnya</button>
        </div>
      </div>

      <!-- ==================== RIWAYAT PEMINJAMAN ==================== -->
      <h3>{{ user?.role === 'admin' ? 'Semua Riwayat Peminjaman (Admin)' : 'Riwayat Peminjaman Saya' }}</h3>

      <!-- Kontrol Pencarian & Filter Riwayat -->
      <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
        <input 
          v-model="searchBorrowing" 
          @input="currentBorrowPage = 1"
          type="text" 
          placeholder="Cari riwayat (judul buku / nama peminjam)..." 
          style="flex: 1; min-width: 200px; padding: 6px;" 
        />
        <select v-model="filterBorrowStatus" @change="currentBorrowPage = 1" style="padding: 6px;">
          <option value="all">Semua Status Peminjaman</option>
          <option value="dipinjam">Dipinjam (Aktif)</option>
          <option value="dikembalikan">Selesai (Dikembalikan)</option>
        </select>
      </div>

      <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
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
          <tr v-if="paginatedBorrowings.length === 0">
            <td :colspan="user?.role === 'admin' ? 6 : 5" align="center" style="color: gray;">Tidak ada riwayat peminjaman yang ditemukan.</td>
          </tr>
          <tr v-for="(borrow, index) in paginatedBorrowings" :key="borrow.id">
            <td align="center">{{ (currentBorrowPage - 1) * itemsPerBorrowPage + index + 1 }}</td>
            <td v-if="user?.role === 'admin'">{{ borrow.user?.name }}</td>
            <td>{{ borrow.book?.title ?? 'Buku Tidak Ditemukan' }}</td>
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
        </tbody>
      </table>

      <!-- Navigasi Pagination Riwayat -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; font-size: 14px;">
        <span>Halaman {{ currentBorrowPage }} dari {{ totalBorrowPages }}</span>
        <div>
          <button 
            @click="currentBorrowPage--" 
            :disabled="currentBorrowPage === 1"
            style="padding: 5px 10px; margin-right: 5px; cursor: pointer;">Sebelumnya</button>
          <button 
            @click="currentBorrowPage++" 
            :disabled="currentBorrowPage >= totalBorrowPages"
            style="padding: 5px 10px; cursor: pointer;">Selanjutnya</button>
        </div>
      </div>

    </div>
  </div>
</template>