<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\Book;
use Illuminate\Http\Request;

class BorrowingController extends Controller
{
    // Menampilkan daftar peminjaman
    public function index(Request $request)
    {
        $borrowings = Borrowing::with(['user', 'book'])->latest()->get();
        return response()->json($borrowings);
    }

    // Proses Peminjaman Buku Baru
    public function store(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
            'borrow_date' => 'required|date',
        ]);

        $book = Book::findOrFail($request->book_id);

        // Cek apakah stok buku masih tersedia
        if ($book->stock < 1) {
            return response()->json([
                'message' => 'Stok buku habis, tidak dapat dipinjam.'
            ], 400);
        }

        // Kurangi stok buku
        $book->decrement('stock');

        // Buat data peminjaman dengan user yang sedang login
        $borrowing = Borrowing::create([
            'user_id' => $request->user()->id,
            'book_id' => $request->book_id,
            'borrow_date' => $request->borrow_date,
            'status' => 'dipinjam',
        ]);

        return response()->json([
            'message' => 'Buku berhasil dipinjam!',
            'data' => $borrowing->load(['user', 'book'])
        ], 201);
    }

    // Pengembalian Buku (Mengubah status & mengembalikan stok)
    public function returnBook($id)
    {
        $borrowing = Borrowing::findOrFail($id);

        if ($borrowing->status === 'dikembalikan') {
            return response()->json([
                'message' => 'Buku ini sudah dikembalikan sebelumnya.'
            ], 400);
        }

        // Ubah status jadi dikembalikan
        $borrowing->update([
            'status' => 'dikembalikan',
            'return_date' => now()->toDateString(),
        ]);

        // Kembalikan stok buku
        $borrowing->book->increment('stock');

        return response()->json([
            'message' => 'Buku berhasil dikembalikan!',
            'data' => $borrowing->load(['user', 'book'])
        ]);
    }
}