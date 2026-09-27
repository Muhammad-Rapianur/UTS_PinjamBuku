<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    // Tampilkan semua daftar buku beserta kategorinya
    public function index()
    {
        $books = Book::with('category')->get();
        return response()->json($books);
    }

    // Tambah buku baru (Biasanya hak akses Admin)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255|not_regex:/^\s*$/',
            'author' => 'required|string|max:255|not_regex:/^\s*$/',
            'category_id' => 'required|exists:categories,id', // TC-07: Referensi tidak sah ditolak
            'stock' => 'required|integer|min:0',
        ]);

        $book = Book::create($request->all());

        return response()->json([
            'message' => 'Buku berhasil ditambahkan',
            'data' => $book
        ], 201);
    }

    // Detail buku berdasarkan ID
    public function show($id)
    {
        $book = Book::with('category')->findOrFail($id);
        return response()->json($book);
    }

    // Update data buku
    public function update(Request $request, $id)
    {
        $book = Book::findOrFail($id);

        $request->validate([
            'category_id' => 'exists:categories,id',
            'title' => 'string|max:255',
            'author' => 'string|max:255',
            'stock' => 'integer|min:0',
        ]);

        $book->update($request->all());

        return response()->json([
            'message' => 'Data buku berhasil diperbarui',
            'data' => $book
        ]);
    }

    // Hapus buku
    public function destroy($id)
    {
        $book = Book::findOrFail($id);
        $book->delete();

        return response()->json([
            'message' => 'Buku berhasil dihapus'
        ]);
    }
}
