<?php

namespace App\Http\Controllers;

use App\Models\Sawit;
use App\Services\RegressionService;
use App\Services\SESService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Exports\SawitExport;
use App\Exports\SawitLimitedExport;
use Maatwebsite\Excel\Facades\Excel;
use PDF;


class SawitController extends Controller
{
    protected $regressionService;
    protected $sesService;

    public function __construct(RegressionService $regressionService, SESService $sesService)
    {
        $this->regressionService = $regressionService;
        $this->sesService = $sesService;
    }

    public function index(Request $request)
    {
        $kecamatan = [
            'Johan Pahlawan',
            'Samatiga',
            'Bubon',
            'Arongan Lambalek',
            'Woyla',
            'Woyla Barat',
            'Woyla Timur',
            'Kaway XVI',
            'Meureubo',
            'Pante Ceureumen',
            'Panton Reu',
            'Sungai Mas',
        ];

        $sawitData = Sawit::all(); 
        
        // Menghitung regresi
        $regressionData = $this->regressionService->calculateRegression();
        $sawitRegression = $regressionData['sawit'];

        // Menghitung SES dengan Alpha dari input
        $alpha = $request->input('alpha', 0.2); // Ambil Alpha dari input form (default 0.2)
        $sawitSES = $this->sesService->calculateSawitSES($alpha); // Gunakan Alpha hanya untuk SES

        return view('pages.sawit-index', compact('sawitRegression', 'sawitSES', 'kecamatan'));
    }

    public function store(Request $request)
    {
        // Cek apakah pengguna adalah admin
        if (Auth::user()->role !== 'admin') {
            return redirect()->route('sawit.index')->with('error', 'Anda tidak memiliki akses untuk menambah data.');
        }

        // Validasi input
        $this->validate($request, [
            'lokasi' => 'required|string',
            'tahun' => 'required|integer',
            'periode' => 'required|string',
            'produksi' => 'required|numeric',
            'alpha' => 'required|numeric|min:0|max:1', // Sesuaikan nama dengan input di form
        ]);

        // Simpan data sawit
        Sawit::create([
            'lokasi' => $request->lokasi,
            'tahun' => $request->tahun,
            'periode' => $request->periode,
            'produksi' => number_format($request->produksi, 2, '.', ''),
            'alpha_ses' => $request->alpha, // Simpan Alpha SES
        ]);

        return redirect()->route('sawit.index')->with('success', 'Data berhasil ditambahkan!');
    }

    public function edit($id)
    {
        // Cek apakah pengguna adalah admin
        if (Auth::user()->role !== 'admin') {
            return redirect()->route('sawit.index')->with('error', 'Anda tidak memiliki akses untuk mengedit data.');
        }

        $sawit = Sawit::findOrFail($id);
        return view('pages.sawit-edit', compact('sawit'));
    }

    public function update(Request $request, $id)
    {
        // Cek apakah pengguna adalah admin
        if (Auth::user()->role !== 'admin') {
            return redirect()->route('sawit.index')->with('error', 'Anda tidak memiliki akses untuk memperbarui data.');
        }

        // Validasi input
        $this->validate($request, [
            'lokasi' => 'required|string',
            'tahun' => 'required|integer',
            'periode' => 'required|string',
            'produksi' => 'required|numeric',
            'alpha_ses' => 'required|numeric|min:0|max:1', // Sesuaikan nama dengan input di form
        ]);

        // Temukan dan perbarui data
        $sawit = Sawit::findOrFail($id);
        $sawit->update([
            'lokasi' => $request->lokasi,
            'tahun' => $request->tahun,
            'periode' => $request->periode,
            'produksi' => number_format($request->produksi, 2, '.', ''),
            'alpha_ses' => $request->alpha, // Update Alpha SES
        ]);

        return redirect()->route('sawit.index')->with('success', 'Data berhasil diperbarui!');
    }

    public function destroy($id)
    {
        // Cek apakah pengguna adalah admin
        if (Auth::user()->role !== 'admin') {
            return redirect()->route('sawit.index')->with('error', 'Anda tidak memiliki akses untuk menghapus data.');
        }

        // Temukan data berdasarkan ID
        $sawit = Sawit::findOrFail($id);
        // Hapus data sawit
        $sawit->delete();

        return redirect()->route('sawit.index')->with('success', 'Data berhasil dihapus!');
    }

    public function grafik()
{
    // Ambil data sawit dari database
    $dataSawit = Sawit::all();

    // Dapatkan periode, produksi, dan prediksi
    $labels = $dataSawit->pluck('tahun')->toArray();
    $produksi = $dataSawit->pluck('produksi')->toArray();

    // Dapatkan prediksi
    $prediksiRegresi = $this->regressionService->predict($dataSawit);
    $prediksiSES = $this->sesService->predict($dataSawit);

    // Cek apakah prediksi terisi
    if (empty($prediksiRegresi) || empty($prediksiSES)) {
        return redirect()->route('sawit.index')->with('error', 'Prediksi tidak tersedia.');
    }

    // Kirim data ke view
    return view('admin.grafik', compact('labels', 'produksi', 'prediksiRegresi', 'prediksiSES'));
}

public function exportPdf(Request $request)
{
    $regressionData = $this->regressionService->calculateRegression();
    $sawitRegression = $regressionData['sawit'];
    $alpha = $request->input('alpha', 0.2); // Ambil Alpha dari input form (default 0.2)
    $sawitSES = $this->sesService->calculateSawitSES($alpha); // Gunakan Alpha hanya untuk SES

    $pdf = PDF::loadView('admin.sawit-pdf', compact('sawitRegression', 'sawitSES'));

    return $pdf->download('hasil-prediksi-sawit.pdf');
}

public function exportCsv(Request $request)
{
    $regressionData = $this->regressionService->calculateRegression();
    $sawitRegression = $regressionData['sawit'];
    $alpha = $request->input('alpha', 0.2); // Ambil Alpha dari input form (default 0.2)
    $sawitSES = $this->sesService->calculateSawitSES($alpha); // Gunakan Alpha hanya untuk SES

    $csvData = [];
    
    // Tambahkan data regresi linear ke CSV
    foreach ($sawitRegression as $location => $data) {
        foreach ($data['predictions'] as $prediction) {
            $csvData[] = [
                'Lokasi' => $location,
                'Model' => 'Regresi Linear',
                'Periode' => $prediction['periode'],
                'Produksi Aktual' => $prediction['produksi'],
                'Produksi Prediksi' => $prediction['predictedY'],
                'APE (%)' => $prediction['APE'],
            ];
        }
    }

    // Tambahkan data SES ke CSV
    foreach ($sawitSES as $location => $data) {
        foreach ($data['predictions'] as $prediction) {
            $csvData[] = [
                'Lokasi' => $location,
                'Model' => 'SES',
                'Periode' => $prediction['periode'],
                'Produksi Aktual' => $prediction['produksi'],
                'Produksi Prediksi' => $prediction['predictedY'],
                'APE (%)' => $prediction['APE'],
            ];
        }
    }

    // Membuat CSV dari array
    $fileName = 'hasil-prediksi-sawit.csv';
    $handle = fopen($fileName, 'w');
    fputcsv($handle, ['Lokasi', 'Model', 'Periode', 'Produksi Aktual', 'Produksi Prediksi', 'APE (%)']);
    foreach ($csvData as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return response()->download($fileName)->deleteFileAfterSend(true);
}

}