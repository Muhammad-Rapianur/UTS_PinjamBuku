<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Book;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Book::insert([
            [
                'category_id' => 1,
                'title' => 'Pemrograman Laravel untuk Pemula',
                'author' => 'Ahmad Developer',
                'stock' => 5,
            ],
            [
                'category_id' => 1,
                'title' => 'Mahir Vue.js 3 dan Pinia',
                'author' => 'Budi Frontend',
                'stock' => 3,
            ],
            [
                'category_id' => 2,
                'title' => 'Desain Basis Data Relasional MySQL',
                'author' => 'Citra Database',
                'stock' => 4,
            ],
        ]);
    }
}
