<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();
# 区块链
Route::get('/block','Block@Index');
Route::get('/block/mine','Block@Mine');
Route::get('/block/creatblock','Block@creatBlock');
Route::get('/block/mine','Block@Mine');
Route::get('/block/transactions/new','Block@TransactionsNew');
Route::get('/block/chain','Block@Chain');
Route::post('/block/nodes/register','Block@NodesRegister');
Route::get('/block/nodes/resolve','Block@NodesResolve');

