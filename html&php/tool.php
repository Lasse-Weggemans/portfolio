<?php
/**
 * Slimme Offerte-tool & Keuzehulp
 * Een volledig functionele single-file PHP-applicatie met real-time calculaties,
 * interactieve ROI-grafieken, PDF-rapportage en CRM Webhook-simulatie.
 */

// Schakel foutrapportage in voor ontwikkelaars
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start sessie om invoer of logs eventueel tijdelijk op te slaan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialiseer CRM-logs in de sessie als ze nog niet bestaan
if (!isset($_SESSION['crm_logs'])) {
    $_SESSION['crm_logs'] = [];
}

// Verwerk een AJAX POST-aanvraag voor de CRM/Webhook simulatie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'submit_lead') {
    header('Content-Type: application/json');
    
    // Verkrijg de ruwe JSON payload
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);
    
    if (!$inputData) {
        echo json_encode(['success' => false, 'error' => 'Ongeldige gegevens ontvangen.']);
        exit;
    }

    // Voeg extra server-side metadata toe
    $inputData['processed_by_php'] = true;
    $inputData['php_session_id'] = session_id();
    $inputData['server_timestamp'] = date('Y-m-d H:i:s');
    
    // Sla de log op in de sessie voor live weergave onderaan de pagina
    $logEntry = [
        'id' => time() . rand(100, 999),
        'time' => date('H:i:s'),
        'event' => 'PHP Backend Received Lead [POST -> CRM & database]',
        'status' => '201 Created & Gesynchroniseerd',
        'payload' => json_encode($inputData, JSON_PRETTY_PRINT)
    ];
    
    array_unshift($_SESSION['crm_logs'], $logEntry);
    
    // Geef een succes-status terug aan de client
    echo json_encode([
        'success' => true,
        'message' => 'Lead succesvol opgeslagen in PHP en doorgeschoten naar CRM.',
        'log' => $logEntry
    ]);
    exit;
}

// Actie om de logs te resetten
if (isset($_GET['action']) && $_GET['action'] === 'clear_logs') {
    $_SESSION['crm_logs'] = [];
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slimme Offerte-tool & Keuzehulp</title>
    <link rel="icon" href="../assets/favicon.png" type="image/png">
    
    <!-- Tailwind CSS voor moderne styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        slate: {
                            850: '#1e293b',
                            950: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Externe bibliotheken voor PDF-generatie en Confetti-effecten -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between font-sans antialiased selection:bg-emerald-500/30 selection:text-emerald-300">

    <!-- Header -->
    <header class="bg-slate-900/90 backdrop-blur-md border-b border-slate-800/80 sticky top-0 z-40 px-4 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3.5">
                <div class="bg-emerald-500 text-slate-950 p-2.5 rounded-2xl shadow-xl shadow-emerald-500/10 font-black tracking-widest text-lg">
                    ECO
                </div>
                <div>
                    <h1 class="font-extrabold text-xl md:text-2xl text-white tracking-tight">Slimme Offerte-tool &amp; Keuzehulp</h1>
                    <p class="text-xs text-slate-400">Verduurzamingscalculator &amp; PHP-CRM Koppeling</p>
                </div>
            </div>
            
            <div class="hidden md:flex items-center space-x-4 text-xs">
                <div class="text-right">
                    <span className="text-slate-400 block">Directe Advieslijn:</span>
                    <span class="text-emerald-400 font-bold font-mono text-sm block">0800 - 4455 220</span>
                </div>
                <div class="w-10 h-10 rounded-2xl bg-slate-800 flex items-center justify-center border border-slate-700">
                    📞
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto w-full px-4 py-6 md:py-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start flex-grow">
        
        <!-- LEFT PANEL: MULTI-STEP INTERACTIVE FORM -->
        <div class="lg:col-span-7 bg-slate-900/40 border border-slate-800/80 rounded-3xl p-6 md:p-8 shadow-2xl backdrop-blur-md">
            
            <!-- Form Step Status Header -->
            <div class="mb-8">
                <div class="flex justify-between items-center text-xs text-slate-400 mb-2">
                    <span class="uppercase tracking-widest text-emerald-400 font-bold">STAP <span id="current-step-num">1</span> VAN 6</span>
                    <span id="current-step-title" class="font-semibold text-slate-200">Verduurzamingskeuze</span>
                </div>
                <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                    <div id="step-progress-bar" class="bg-gradient-to-r from-emerald-500 to-teal-400 h-full transition-all duration-500 ease-out" style="width: 16.66%;"></div>
                </div>
            </div>

            <form id="quote-wizard-form" onsubmit="handleFormSubmit(event)">
                
                <!-- STAP 1: Project Type selectie -->
                <div id="step-1" class="step-container space-y-6">
                    <div>
                        <h2 class="text-2xl font-extrabold text-white mb-2 tracking-tight">Waar wilt u direct op gaan besparen?</h2>
                        <p class="text-slate-400 mb-6 text-sm">Selecteer de verduurzamingstak voor uw woning. Onze gecombineerde pakketten leveren direct 10% combikorting op.</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Zonnepanelen -->
                        <div onclick="setProjectType('solar')" id="card-project-solar" class="project-card p-5 rounded-2xl border-2 text-left transition-all duration-200 cursor-pointer border-emerald-500 bg-emerald-500/10 shadow-lg shadow-emerald-500/5 relative">
                            <div class="p-3 rounded-xl inline-block mb-4 bg-emerald-500 text-slate-950 project-icon-wrapper">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m0-12.728l.707.707m12.728 12.728l.707.707M12 8a4 4 0 100 8 4 4 0 000-8z" /></svg>
                            </div>
                            <h3 class="font-extrabold text-lg text-white mb-1">Zonnepanelen</h3>
                            <p class="text-xs text-slate-400 leading-relaxed">Direct schone stroom opwekken op uw eigen dak om maandelijkse energielasten te elimineren.</p>
                            <div class="check-badge absolute top-4 right-4 bg-emerald-500 text-slate-950 rounded-full p-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                        </div>

                        <!-- Warmtepomp -->
                        <div onclick="setProjectType('heatpump')" id="card-project-heatpump" class="project-card p-5 rounded-2xl border-2 text-left transition-all duration-200 cursor-pointer border-slate-800 bg-slate-900/60 relative">
                            <div class="p-3 rounded-xl inline-block mb-4 bg-slate-800 text-slate-300 project-icon-wrapper">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                            </div>
                            <h3 class="font-extrabold text-lg text-white mb-1">Warmtepomp</h3>
                            <p class="text-xs text-slate-400 leading-relaxed">Vervang of minimaliseer gasketelverbruik. Bespaar tot wel 75% op gasrekeningen.</p>
                            <div class="check-badge absolute top-4 right-4 bg-emerald-500 text-slate-950 rounded-full p-0.5 hidden">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                        </div>

                        <!-- Isolatie -->
                        <div onclick="setProjectType('insulation')" id="card-project-insulation" class="project-card p-5 rounded-2xl border-2 text-left transition-all duration-200 cursor-pointer border-slate-800 bg-slate-900/60 relative">
                            <div class="p-3 rounded-xl inline-block mb-4 bg-slate-800 text-slate-300 project-icon-wrapper">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                            </div>
                            <h3 class="font-extrabold text-lg text-white mb-1">Spouw- &amp; Dakisolatie</h3>
                            <p class="text-xs text-slate-400 leading-relaxed">Houd warmte binnen en de kou buiten. Directe comfortverhoging en tochtvermindering.</p>
                            <div class="check-badge absolute top-4 right-4 bg-emerald-500 text-slate-950 rounded-full p-0.5 hidden">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                        </div>

                        <!-- Hybrid -->
                        <div onclick="setProjectType('hybrid')" id="card-project-hybrid" class="project-card p-5 rounded-2xl border-2 text-left transition-all duration-200 cursor-pointer border-slate-800 bg-slate-900/60 relative">
                            <span class="absolute top-4 left-4 bg-emerald-400 text-slate-950 font-black px-2.5 py-0.5 rounded-lg text-[9px] uppercase tracking-wider shadow">COMBIDEAL -10%</span>
                            <div class="p-3 rounded-xl inline-block mb-4 bg-slate-800 text-slate-300 project-icon-wrapper mt-3">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m0-12.728l.707.707m12.728 12.728l.707.707M12 8a4 4 0 100 8 4 4 0 000-8z" /></svg>
                            </div>
                            <h3 class="font-extrabold text-lg text-white mb-1">Zonnepanelen + Warmtepomp</h3>
                            <p class="text-xs text-slate-400 leading-relaxed">De ultieme besparingsbundel. Zonnepanelen wekken de benodigde warmtepomp-stroom direct zelf op.</p>
                            <div class="check-badge absolute top-4 right-4 bg-emerald-500 text-slate-950 rounded-full p-0.5 hidden">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAP 2: Daksituatie & Oriëntatie -->
                <div id="step-2" class="step-container space-y-6 hidden">
                    <div>
                        <h2 class="text-2xl font-extrabold text-white mb-2 tracking-tight">Uw Daksituatie &amp; Oriëntatie</h2>
                        <p class="text-slate-400 mb-6 text-sm">Dakbedekking en zonligging bepalen in sterke mate de constructietijd en de zonne-energie opbrengstcoëfficiënt.</p>
                    </div>

                    <div class="space-y-6">
                        <!-- Dakconstructie -->
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-400 tracking-wider mb-2.5">Type Dakconstructie:</label>
                            <div class="grid grid-cols-3 gap-3">
                                <button type="button" onclick="setRoofType('schuin')" id="btn-roof-schuin" class="roof-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-emerald-500 bg-emerald-500/10 text-emerald-400">🏠 Schuindak</button>
                                <button type="button" onclick="setRoofType('plat')" id="btn-roof-plat" class="roof-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-slate-800 bg-slate-900/40 text-slate-300 hover:border-slate-700">🏢 Platdak</button>
                                <button type="button" onclick="setRoofType('geen')" id="btn-roof-geen" class="roof-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-slate-800 bg-slate-900/40 text-slate-300 hover:border-slate-700">🌳 Vrijstaand</button>
                            </div>
                        </div>

                        <!-- Dakbedekking -->
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-400 tracking-wider mb-2.5">Dakbedekking Materiaal:</label>
                            <div class="grid grid-cols-3 gap-3">
                                <button type="button" onclick="setRoofMaterial('pannen')" id="btn-mat-pannen" class="mat-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-emerald-500 bg-emerald-500/10 text-emerald-400">🧱 Dakpannen</button>
                                <button type="button" onclick="setRoofMaterial('bitumen')" id="btn-mat-bitumen" class="mat-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-slate-800 bg-slate-900/40 text-slate-300 hover:border-slate-700">🕳️ Bitumen</button>
                                <button type="button" onclick="setRoofMaterial('metaal')" id="btn-mat-metaal" class="mat-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-slate-800 bg-slate-900/40 text-slate-300 hover:border-slate-700">🛡️ Metaal/Platen</button>
                            </div>
                        </div>

                        <!-- Oriëntatie -->
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-400 tracking-wider mb-2.5">Dak-Oriëntatie (Zonligging):</label>
                            <div class="grid grid-cols-3 gap-3">
                                <button type="button" onclick="setOrientation('zuid')" id="btn-ori-zuid" class="ori-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-emerald-500 bg-emerald-500/10 text-emerald-400">☀️ Zuiden (100%)</button>
                                <button type="button" onclick="setOrientation('oost-west')" id="btn-ori-oost-west" class="ori-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-slate-800 bg-slate-900/40 text-slate-300 hover:border-slate-700">⛅ Oost-West (82%)</button>
                                <button type="button" onclick="setOrientation('noord')" id="btn-ori-noord" class="ori-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-slate-800 bg-slate-900/40 text-slate-300 hover:border-slate-700">❄️ Noorden (55%)</button>
                            </div>
                        </div>

                        <div class="bg-emerald-500/5 border border-emerald-500/10 p-4 rounded-2xl flex items-start space-x-3">
                            <span class="text-xl">☀️</span>
                            <div class="text-xs text-slate-400 leading-relaxed">
                                <strong class="text-emerald-400 block mb-0.5">Zonligging invloed:</strong>
                                Een zuidgerichte opstelling genereert maximale opbrengsten. Heeft u een Oost-West dak? Dan spreiden we de opwekking ideaal over de ochtend en avond.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAP 3: Huisoppervlak & Jaarverbruik -->
                <div id="step-3" class="step-container space-y-6 hidden">
                    <div>
                        <h2 class="text-2xl font-extrabold text-white mb-2 tracking-tight">Energieverbruik &amp; Oppervlakte</h2>
                        <p class="text-slate-400 mb-6 text-sm">Stel de schuifregelaars in op uw dakoppervlakte en geschatte jaarverbruik om uw netto voordeel te berekenen.</p>
                    </div>

                    <div class="space-y-6">
                        <!-- Oppervlakte -->
                        <div>
                            <div class="flex justify-between items-center mb-1.5">
                                <label class="text-xs font-semibold uppercase text-slate-400 tracking-wider">Beschikbaar dakoppervlak:</label>
                                <div class="flex items-center space-x-2">
                                    <input type="number" id="input-area" oninput="updateAreaSlider(this.value)" value="45" class="bg-slate-900 w-16 text-center text-emerald-400 font-bold text-sm px-1 py-0.5 border border-slate-800 rounded-lg focus:outline-none">
                                    <span class="text-sm font-bold text-emerald-400">m²</span>
                                </div>
                            </div>
                            <input type="range" id="slider-area" min="10" max="180" step="5" value="45" oninput="updateAreaInput(this.value)" class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-emerald-500">
                            <div class="flex justify-between text-[10px] text-slate-500 mt-1">
                                <span>10 m² (Klein)</span>
                                <span>95 m² (Gemiddeld)</span>
                                <span>180 m² (Groot)</span>
                            </div>
                        </div>

                        <!-- Energieverbruik Elementen (verbergen bij isolatie) -->
                        <div id="consumption-fields" class="space-y-6">
                            <!-- Stroomverbruik -->
                            <div>
                                <div class="flex justify-between items-center mb-1.5">
                                    <label class="text-xs font-semibold uppercase text-slate-400 tracking-wider">Huidig Jaarverbruik Elektra:</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" id="input-elec" oninput="updateElecSlider(this.value)" value="3500" class="bg-slate-900 w-20 text-center text-emerald-400 font-bold text-sm px-1 py-0.5 border border-slate-800 rounded-lg focus:outline-none">
                                        <span class="text-sm font-bold text-emerald-400">kWh</span>
                                    </div>
                                </div>
                                <input type="range" id="slider-elec" min="800" max="9000" step="100" value="3500" oninput="updateElecInput(this.value)" class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-emerald-500">
                            </div>

                            <!-- Gasverbruik -->
                            <div>
                                <div class="flex justify-between items-center mb-1.5">
                                    <label class="text-xs font-semibold uppercase text-slate-400 tracking-wider">Huidig Jaarverbruik Gas:</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" id="input-gas" oninput="updateGasSlider(this.value)" value="1200" class="bg-slate-900 w-20 text-center text-emerald-400 font-bold text-sm px-1 py-0.5 border border-slate-800 rounded-lg focus:outline-none">
                                        <span class="text-sm font-bold text-emerald-400">m³</span>
                                    </div>
                                </div>
                                <input type="range" id="slider-gas" min="400" max="4000" step="50" value="1200" oninput="updateGasInput(this.value)" class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-emerald-500">
                            </div>
                        </div>

                        <!-- Geavanceerde Energietarieven -->
                        <div class="border-t border-slate-800/80 pt-4 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] text-slate-500 uppercase font-semibold mb-1">Uw Stroomtarief (€/kWh):</label>
                                <input type="number" step="0.01" id="rate-elec" value="0.35" oninput="updateRates()" class="w-full bg-slate-900/60 border border-slate-800 rounded-lg py-2 px-3 text-sm text-slate-300 focus:outline-none focus:border-emerald-500">
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-500 uppercase font-semibold mb-1">Uw Gastarief (€/m³):</label>
                                <input type="number" step="0.01" id="rate-gas" value="1.35" oninput="updateRates()" class="w-full bg-slate-900/60 border border-slate-800 rounded-lg py-2 px-3 text-sm text-slate-300 focus:outline-none focus:border-emerald-500">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAP 4: Slimme Systeembundelingen -->
                <div id="step-4" class="step-container space-y-6 hidden">
                    <div>
                        <h2 class="text-2xl font-extrabold text-white mb-2 tracking-tight">Slimme Systeembundelingen</h2>
                        <p class="text-slate-400 mb-6 text-sm">Integreer extra technologische upgrades om direct uw autarkie en ROI te maximaliseren.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Thuisaccu -->
                        <div onclick="toggleAddon('hasBattery')" id="addon-hasBattery" class="addon-card p-4 rounded-xl border-2 text-left flex items-start space-x-3 transition-all cursor-pointer border-slate-800 bg-slate-900/60 hover:border-slate-700">
                            <div class="addon-icon p-2 rounded-lg bg-slate-800 text-slate-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                            <div>
                                <h4 class="font-extrabold text-xs text-white flex items-center justify-between">
                                    <span>Thuisaccu Systeem</span>
                                    <span class="text-emerald-400 font-mono text-[10px] ml-1.5">+ €4.200</span>
                                </h4>
                                <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">Sla uw eigen opgewekte zonne-energie overdag op voor gebruik in de avonduren.</p>
                            </div>
                        </div>

                        <!-- Laadpaal -->
                        <div onclick="toggleAddon('hasCharger')" id="addon-hasCharger" class="addon-card p-4 rounded-xl border-2 text-left flex items-start space-x-3 transition-all cursor-pointer border-slate-800 bg-slate-900/60 hover:border-slate-700">
                            <div class="addon-icon p-2 rounded-lg bg-slate-800 text-slate-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                            </div>
                            <div>
                                <h4 class="font-extrabold text-xs text-white flex items-center justify-between">
                                    <span>Slimme Laadpaal</span>
                                    <span class="text-emerald-400 font-mono text-[10px] ml-1.5">+ €1.150</span>
                                </h4>
                                <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">Laad uw elektrische auto slim, veilig en 100% direct op uw eigen zonne-energie op.</p>
                            </div>
                        </div>

                        <!-- Micro-Omvormers -->
                        <div onclick="toggleAddon('premiumInverter')" id="addon-premiumInverter" class="addon-card p-4 rounded-xl border-2 text-left flex items-start space-x-3 transition-all cursor-pointer border-slate-800 bg-slate-900/60 hover:border-slate-700">
                            <div class="addon-icon p-2 rounded-lg bg-slate-800 text-slate-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                            </div>
                            <div>
                                <h4 class="font-extrabold text-xs text-white flex items-center justify-between">
                                    <span>Micro-Omvormers</span>
                                    <span class="text-emerald-400 font-mono text-[10px] ml-1.5">+ €600</span>
                                </h4>
                                <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">Optimaliseert elk zonnepaneel individueel. Geen opbrengstverlies bij schaduw.</p>
                            </div>
                        </div>

                        <!-- Smart Energy Manager -->
                        <div onclick="toggleAddon('smartAutomation')" id="addon-smartAutomation" class="addon-card p-4 rounded-xl border-2 text-left flex items-start space-x-3 transition-all cursor-pointer border-slate-800 bg-slate-900/60 hover:border-slate-700">
                            <div class="addon-icon p-2 rounded-lg bg-slate-800 text-slate-300">
                                <svg class="w-5 h-5 animate-spin" style="animation-duration: 8s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </div>
                            <div>
                                <h4 class="font-extrabold text-xs text-white flex items-center justify-between">
                                    <span>Smart Energy Manager</span>
                                    <span class="text-emerald-400 font-mono text-[10px] ml-1.5">+ €350</span>
                                </h4>
                                <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">Laat apparaten en accu automatisch handelen op basis van dynamische spot-stroomprijzen.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAP 5: Contactgegevens -->
                <div id="step-5" class="step-container space-y-4 hidden">
                    <div>
                        <h2 class="text-2xl font-extrabold text-white mb-2 tracking-tight">Rapportage Bestemming</h2>
                        <p class="text-slate-400 mb-6 text-sm">Vul uw contactgegevens in om het indicatieve verduurzamingsvoorstel direct te downloaden en te verzenden.</p>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Voornaam</label>
                                <input type="text" id="firstName" placeholder="bijv. Jan" class="w-full bg-slate-950 border border-slate-800 focus:ring-1 focus:ring-emerald-500 rounded-xl px-4 py-3 text-white placeholder-slate-600 text-sm focus:outline-none">
                                <span id="error-firstName" class="text-red-500 text-[10px] mt-1 block hidden"></span>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Achternaam</label>
                                <input type="text" id="lastName" placeholder="de Vries" class="w-full bg-slate-950 border border-slate-800 focus:ring-1 focus:ring-emerald-500 rounded-xl px-4 py-3 text-white placeholder-slate-600 text-sm focus:outline-none">
                                <span id="error-lastName" class="text-red-500 text-[10px] mt-1 block hidden"></span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">E-mailadres</label>
                            <input type="email" id="email" placeholder="jandevries@outlook.com" class="w-full bg-slate-950 border border-slate-800 focus:ring-1 focus:ring-emerald-500 rounded-xl px-4 py-3 text-white placeholder-slate-600 text-sm focus:outline-none">
                            <span id="error-email" class="text-red-500 text-[10px] mt-1 block hidden"></span>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Telefoonnummer</label>
                            <input type="tel" id="phone" placeholder="06 - 1234 5678" class="w-full bg-slate-950 border border-slate-800 focus:ring-1 focus:ring-emerald-500 rounded-xl px-4 py-3 text-white placeholder-slate-600 text-sm focus:outline-none">
                            <span id="error-phone" class="text-red-500 text-[10px] mt-1 block hidden"></span>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Postcode</label>
                                <input type="text" id="postalCode" placeholder="1234 AB" class="w-full bg-slate-950 border border-slate-800 focus:ring-1 focus:ring-emerald-500 rounded-xl px-4 py-3 text-white placeholder-slate-600 text-sm focus:outline-none">
                                <span id="error-postalCode" class="text-red-500 text-[10px] mt-1 block hidden"></span>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Huisnummer</label>
                                <input type="text" id="houseNumber" placeholder="12-A" class="w-full bg-slate-950 border border-slate-800 focus:ring-1 focus:ring-emerald-500 rounded-xl px-4 py-3 text-white placeholder-slate-600 text-sm focus:outline-none">
                                <span id="error-houseNumber" class="text-red-500 text-[10px] mt-1 block hidden"></span>
                            </div>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-start space-x-3 cursor-pointer">
                                <input type="checkbox" id="agreeTerms" class="mt-1 accent-emerald-500 h-4 w-4 rounded">
                                <span class="text-xs text-slate-400">
                                    Ja, ik ga akkoord met de verwerking van mijn gegevens om deze dynamische prijsberekening op te slaan en contact te leggen.
                                </span>
                            </label>
                            <span id="error-agreeTerms" class="text-red-500 text-[10px] mt-1 block hidden"></span>
                        </div>

                        <button type="submit" id="btn-submit-form" class="w-full mt-4 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-extrabold py-4 rounded-xl shadow-lg shadow-emerald-500/10 transition duration-200 flex items-center justify-center space-x-2 text-base">
                            <span>Mijn Offerte-Rapportage Aanvragen</span>
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                        </button>
                    </div>
                </div>

                <!-- STAP 6: Bedankt- & Downloadpagina -->
                <div id="step-6" class="step-container text-center py-6 hidden">
                    <div class="w-16 h-16 bg-emerald-500/10 text-emerald-400 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-emerald-500/20 shadow-inner">
                        <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <h2 class="text-3xl font-black text-white mb-2 tracking-tight">Offerte is gegenereerd!</h2>
                    <p class="text-slate-400 text-sm max-w-md mx-auto mb-8">
                        Beste <strong id="success-first-name" class="text-white">Bezoeker</strong>, uw verduurzamingsvoorstel staat klaar om gedownload te worden. De CRM-payload is direct doorgestuurd.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                        <button type="button" onclick="generatePDF()" class="bg-slate-800 hover:bg-slate-700 text-white font-extrabold py-4 px-6 rounded-2xl flex items-center justify-center border border-slate-700 transition duration-150 shadow-md">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <span id="pdf-btn-text">Download Offerte PDF</span>
                        </button>

                        <button type="button" onclick="resetWizard()" class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold py-4 px-6 rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-500/20 transition duration-150">
                            <span>Nieuwe Berekening</span>
                        </button>
                    </div>

                    <div class="bg-slate-950/80 rounded-2xl p-5 border border-slate-800 text-left">
                        <h4 class="font-bold text-sm text-white mb-2 flex items-center">
                            <span class="mr-2">🔌</span> PHP Backend &amp; CRM Status
                        </h4>
                        <p class="text-xs text-slate-400 leading-relaxed mb-3">
                            Er is succesvol een JSON-pakket klaargezet en via PHP doorgesluisd naar de sessie logs en gesimuleerde CRM-koppelingen op de server.
                        </p>
                        <div class="flex items-center space-x-2 text-xs text-emerald-400 font-semibold">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
                            <span>Lead succesvol verwerkt en gekoppeld (HTTP STATUS: 201 Created)</span>
                        </div>
                    </div>
                </div>

                <!-- NAVIGATIEKNOPPEN (Verbergen op stap 5 (verzenden) en stap 6 (klaar)) -->
                <div id="wizard-navigation" class="flex justify-between mt-8 pt-6 border-t border-slate-800/60">
                    <button type="button" id="btn-prev" onclick="prevStep()" class="flex items-center font-bold text-sm py-2.5 px-4 rounded-xl border border-slate-800 text-slate-300 transition opacity-30 cursor-not-allowed">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        <span>Vorige</span>
                    </button>

                    <button type="button" id="btn-next" onclick="nextStep()" class="flex items-center font-bold text-sm py-2.5 px-5 rounded-xl bg-emerald-500 text-slate-950 hover:bg-emerald-400 shadow-lg shadow-emerald-500/10 transition">
                        <span>Volgende</span>
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </button>
                </div>
            </form>
        </div>

        <!-- RIGHT PANEL: LIVE PRICE ESTIMATOR & GRAPH PREVIEW -->
        <div class="lg:col-span-5 space-y-6 lg:sticky lg:top-24">
            
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-500/5 rounded-full blur-3xl"></div>
                
                <h3 class="font-extrabold text-lg text-white mb-4 flex items-center justify-between">
                    <span>Live Calculatie</span>
                    <span class="text-xs bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2.5 py-0.5 rounded font-mono font-bold tracking-widest uppercase">
                        Real-Time
                    </span>
                </h3>

                <!-- Calculations Breakdown -->
                <div class="space-y-4">
                    <div class="flex justify-between items-baseline text-sm text-slate-400">
                        <span>Bruto kosten indicatie:</span>
                        <span class="font-mono text-white font-bold text-base">
                            € <span id="calc-base-price">0</span>
                        </span>
                    </div>

                    <div class="flex justify-between items-baseline text-sm">
                        <span class="text-slate-400 flex items-center">
                            Toepasbare subsidies:
                            <span class="ml-1 group relative cursor-pointer text-slate-500">
                                <svg class="w-4 h-4 inline mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span class="absolute bottom-full left-1/2 -translate-x-1/2 bg-slate-950 border border-slate-800 text-[10px] leading-relaxed text-slate-300 w-48 p-2 rounded-lg hidden group-hover:block shadow-2xl z-50">
                                    In NL betaalt u 0% BTW over zonnepanelen. Warmtepompen en isolatie vallen onder de ISDE-subsidiepot.
                                </span>
                            </span>
                        </span>
                        <span class="font-mono text-emerald-400 font-bold text-base">
                            - € <span id="calc-subsidy">0</span>
                        </span>
                    </div>

                    <div class="border-t border-slate-800/80 pt-3.5 flex justify-between items-baseline">
                        <span class="font-extrabold text-slate-200">Netto investering:</span>
                        <div class="text-right">
                            <span class="text-3xl font-black font-mono text-emerald-400 block tracking-tight">
                                € <span id="calc-net-investment">0</span>
                            </span>
                            <span class="text-[10px] text-slate-500 block">incl. 0% BTW &amp; subsidievoordeel</span>
                        </div>
                    </div>

                    <!-- Carbon savings & visual KPIs -->
                    <div class="bg-slate-950/60 rounded-2xl p-4 border border-slate-800 grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase tracking-wider block mb-0.5">Jaarbesparing</span>
                            <span class="text-lg font-extrabold text-white font-mono flex items-center">
                                € <span id="calc-yearly-savings">0</span>
                                <span class="text-[11px] text-emerald-400 ml-1">/jr</span>
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase tracking-wider block mb-0.5">Terugverdientijd</span>
                            <span class="text-lg font-extrabold text-white font-mono">
                                ~ <span id="calc-payback">0</span> <span class="text-xs text-slate-400 font-normal">jaar</span>
                            </span>
                        </div>
                    </div>

                    <div class="bg-emerald-950/20 rounded-xl p-3 border border-emerald-500/10 flex items-center space-x-3">
                        <svg class="w-5 h-5 text-emerald-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                        <div class="text-xs text-slate-300 leading-relaxed">
                            Hiermee bespaart u jaarlijks <strong id="calc-co2" class="text-emerald-400">0</strong> kg CO₂. Dit staat gelijk aan het planten van <strong id="calc-trees" class="text-emerald-400">0</strong> bomen!
                        </div>
                    </div>

                    <div class="flex items-center space-x-3 text-xs text-slate-400 bg-slate-950/30 p-3 rounded-xl border border-slate-800/50">
                        <span class="text-lg">📅</span>
                        <span>Geraamde installatietijd: ca. <strong id="calc-weeks">0</strong> weken.</span>
                    </div>
                </div>
            </div>

            <!-- ROI Graph -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h4 class="font-extrabold text-sm text-white flex items-center">
                            <span class="mr-2">📈</span> Financieel Prognose-model (15 Jaar)
                        </h4>
                        <p class="text-[11px] text-slate-400">Interactief: beweeg uw muis over de kolommen om de winst te peilen.</p>
                    </div>
                </div>

                <!-- Dynamic Column Chart -->
                <div class="bg-slate-950 rounded-2xl p-4 border border-slate-800 relative">
                    <div id="chart-bars-container" class="flex justify-between items-end h-32 pt-6 relative">
                        <!-- Bars will be dynamically injected here by Javascript -->
                    </div>
                    
                    <!-- Horizontal baseline zero level -->
                    <div class="absolute left-0 right-0 h-0.5 bg-slate-800 bottom-[54px] z-0 pointer-events-none"></div>

                    <!-- Dynamic hover output label -->
                    <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between min-h-[36px]">
                        <span id="chart-hover-info" class="text-xs text-slate-500 italic block mx-auto text-center w-full">
                            Beweeg over de kolommen om de cumulatieve winst te bekijken
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- DEVELOPER HUB (CRM Webhook Logs) -->
    <footer class="bg-slate-950 border-t border-slate-900 p-6 md:p-8 mt-12">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-4 pb-4 border-b border-slate-900">
                <div>
                    <h4 class="font-extrabold text-white text-base flex items-center">
                        <span class="p-1.5 bg-amber-500/10 text-amber-500 rounded-lg mr-2 text-sm">🛠️</span>
                        PHP Backend Webhook Hub (CRM Live logs)
                    </h4>
                    <p class="text-xs text-slate-400">Verifieer de real-time JSON payloads opgeslagen in PHP sessies.</p>
                </div>
                <div class="mt-3 md:mt-0 flex items-center space-x-2">
                    <a href="?action=clear_logs" class="text-xs bg-slate-900 hover:bg-slate-800 text-slate-300 px-3 py-1.5 rounded-lg border border-slate-800 transition">
                        Logs Leegmaken
                    </a>
                    <span class="px-2.5 py-1.5 text-[10px] uppercase font-mono font-bold bg-slate-900 text-slate-400 rounded border border-slate-800">
                        JSON REST API
                    </span>
                    <span class="px-2.5 py-1.5 text-[10px] uppercase font-mono font-bold bg-slate-900 text-emerald-400 rounded border border-slate-800">
                        PHP SESSIE v3
                    </span>
                </div>
            </div>

            <div class="space-y-3" id="crm-logs-list">
                <?php if (empty($_SESSION['crm_logs'])): ?>
                    <div class="bg-slate-950 border border-slate-900 rounded-2xl p-6 text-center text-slate-500 text-xs font-mono">
                        [Wachten op lead-activatie...] Voltooi stap 5 van de wizard om een live PHP webhook levering te simuleren.
                    </div>
                <?php else: ?>
                    <?php foreach ($_SESSION['crm_logs'] as $log): ?>
                        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl overflow-hidden font-mono text-xs shadow-2xl">
                            <div class="bg-slate-850 px-4 py-2.5 flex items-center justify-between border-b border-slate-800 text-slate-300">
                                <span class="flex items-center text-emerald-400 font-bold">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>
                                    <?= htmlspecialchars($log['event']) ?>
                                </span>
                                <span class="text-[10px] text-slate-500"><?= htmlspecialchars($log['time']) ?> | STATUS: <?= htmlspecialchars($log['status']) ?></span>
                            </div>
                            <pre class="p-4 text-emerald-400/90 bg-slate-950 overflow-x-auto leading-relaxed max-h-72 selection:bg-slate-800"><code><?= htmlspecialchars($log['payload']) ?></code></pre>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <!-- INTERACTIEVE LOGICA (JAVASCRIPT) -->
    <script>
        // State Management
        let step = 1;
        const formData = {
            projectType: 'solar', 
            roofType: 'schuin', 
            roofMaterial: 'pannen', 
            roofOrientation: 'zuid', 
            area: 45, 
            energyConsumption: 3500, 
            gasConsumption: 1200, 
            electricityRate: 0.35, 
            gasRate: 1.35, 
            hasBattery: false,
            hasCharger: false,
            premiumInverter: false,
            smartAutomation: false,
            firstName: '',
            lastName: '',
            email: '',
            phone: '',
            postalCode: '',
            houseNumber: '',
            agreeTerms: false
        };

        let calculatedValues = {};

        // Wizards navigatie titels
        const stepTitles = {
            1: "Verduurzamingskeuze",
            2: "Daksituatie & Oriëntatie",
            3: "Huisoppervlak & Jaarverbruik",
            4: "Slimme Upgrades & Uitbreidingen",
            5: "Contactgegevens & Verwerking",
            6: "Uw Offerte & Adviesrapportage"
        };

        // Live calculator herberekenen
        function runCalculations() {
            let basePrice = 0;
            let subsidy = 0;
            let yearlySavings = 0;
            let co2Savings = 0; 
            let installWeeks = 4;

            let orientationFactor = 1.0;
            if (formData.roofOrientation === 'oost-west') orientationFactor = 0.82;
            if (formData.roofOrientation === 'noord') orientationFactor = 0.55;

            // 1. Core calculaties per type
            if (formData.projectType === 'solar') {
                const maxPanelsFit = Math.floor(formData.area / 1.7);
                const idealPanelsCount = Math.min(maxPanelsFit, Math.ceil(formData.energyConsumption / 380));
                const finalPanels = Math.max(4, Math.min(idealPanelsCount, 32));
                
                basePrice = 2800 + (finalPanels * 420);
                subsidy = 0; // 0% BTW regeling NL
                
                const annualKwhYield = finalPanels * 410 * 0.88 * orientationFactor;
                yearlySavings = annualKwhYield * formData.electricityRate;
                co2Savings = annualKwhYield * 0.45;
                installWeeks = 3;

            } else if (formData.projectType === 'heatpump') {
                const isLargeHouse = formData.gasConsumption > 1800;
                basePrice = isLargeHouse ? 11500 : 7500;
                subsidy = isLargeHouse ? 3450 : 2850; 
                
                const cop = 4.1;
                const gasKwhEquiv = formData.gasConsumption * 9.77; 
                const electricNeeded = gasKwhEquiv / cop;
                
                const oldGasCost = formData.gasConsumption * formData.gasRate;
                const newElectricCost = electricNeeded * formData.electricityRate;
                
                yearlySavings = Math.max(300, oldGasCost - newElectricCost);
                const savedGasCo2 = formData.gasConsumption * 1.89;
                const addedElectricCo2 = electricNeeded * 0.45;
                co2Savings = Math.max(100, savedGasCo2 - addedElectricCo2);
                installWeeks = 5;

            } else if (formData.projectType === 'insulation') {
                basePrice = formData.area * 48 + 1200;
                subsidy = basePrice * 0.30; 
                
                const gasSaved = formData.gasConsumption * 0.28;
                yearlySavings = gasSaved * formData.gasRate;
                co2Savings = gasSaved * 1.89;
                installWeeks = 2;

            } else if (formData.projectType === 'hybrid') {
                const maxPanelsFit = Math.floor(formData.area / 1.7);
                const idealPanelsCount = Math.min(maxPanelsFit, Math.ceil(formData.energyConsumption / 380));
                const finalPanels = Math.max(6, Math.min(idealPanelsCount, 24));
                
                const solarPrice = 2800 + (finalPanels * 420);
                const pumpPrice = formData.gasConsumption > 1800 ? 11000 : 7200;
                
                basePrice = (solarPrice + pumpPrice) * 0.90; // 10% combikorting
                subsidy = formData.gasConsumption > 1800 ? 3450 : 2850; 

                const annualKwhYield = finalPanels * 410 * 0.88 * orientationFactor;
                const solarSavings = annualKwhYield * formData.electricityRate;
                
                const cop = 4.1;
                const gasKwhEquiv = formData.gasConsumption * 9.77;
                const electricNeeded = gasKwhEquiv / cop;
                const oldGasCost = formData.gasConsumption * formData.gasRate;
                
                const netElectricNeeded = Math.max(0, electricNeeded - (annualKwhYield * 0.4));
                const newElectricCost = netElectricNeeded * formData.electricityRate;
                
                yearlySavings = (oldGasCost + solarSavings) - newElectricCost;
                co2Savings = (annualKwhYield * 0.45) + (formData.gasConsumption * 1.89) - (netElectricNeeded * 0.45);
                installWeeks = 6;
            }

            // 2. Add-on Modifiers
            if (formData.hasBattery) {
                basePrice += 4200;
                if (formData.projectType === 'solar' || formData.projectType === 'hybrid') {
                    yearlySavings += 290;
                } else {
                    yearlySavings += 120;
                }
            }
            if (formData.hasCharger) basePrice += 1150;
            if (formData.premiumInverter) {
                basePrice += 600;
                yearlySavings += 55;
            }
            if (formData.smartAutomation) {
                basePrice += 350;
                yearlySavings += 85;
            }

            if (formData.roofType === 'plat') basePrice += 380;

            const netInvestment = Math.max(0, basePrice - subsidy);
            const paybackPeriod = yearlySavings > 0 ? (netInvestment / yearlySavings).toFixed(1) : '0.0';
            const treesEquivalent = Math.round(co2Savings / 20);

            calculatedValues = {
                basePrice: Math.round(basePrice),
                subsidy: Math.round(subsidy),
                netInvestment: Math.round(netInvestment),
                yearlySavings: Math.round(yearlySavings),
                paybackPeriod,
                installWeeks,
                co2Savings: Math.round(co2Savings),
                treesEquivalent
            };

            updateUI();
        }

        // Synchroniseer data met HTML elements
        function updateUI() {
            document.getElementById('calc-base-price').innerText = calculatedValues.basePrice.toLocaleString('nl-NL');
            document.getElementById('calc-subsidy').innerText = calculatedValues.subsidy.toLocaleString('nl-NL');
            document.getElementById('calc-net-investment').innerText = calculatedValues.netInvestment.toLocaleString('nl-NL');
            document.getElementById('calc-yearly-savings').innerText = calculatedValues.yearlySavings.toLocaleString('nl-NL');
            document.getElementById('calc-payback').innerText = calculatedValues.paybackPeriod;
            document.getElementById('calc-co2').innerText = calculatedValues.co2Savings.toLocaleString('nl-NL');
            document.getElementById('calc-trees').innerText = calculatedValues.treesEquivalent;
            document.getElementById('calc-weeks').innerText = calculatedValues.installWeeks;

            // Verberg of toon energieverbruik velden op basis van projecttype
            const consumptionFields = document.getElementById('consumption-fields');
            if (formData.projectType === 'insulation') {
                consumptionFields.classList.add('hidden');
            } else {
                consumptionFields.classList.remove('hidden');
            }

            renderGraph();
        }

        // ROI dynamic chart generator (SVG renderen via Javascript)
        function renderGraph() {
            const container = document.getElementById('chart-bars-container');
            container.innerHTML = '';

            const years = Array.from({ length: 16 }, (_, i) => i);
            const chartPoints = years.map(year => {
                const cumulativeValue = -calculatedValues.netInvestment + (calculatedValues.yearlySavings * year);
                return { year, value: Math.round(cumulativeValue) };
            });

            const maxVal = Math.max(...chartPoints.map(p => Math.abs(p.value)));

            chartPoints.forEach(point => {
                const isPositive = point.value >= 0;
                const barHeight = Math.max(4, Math.min(100, (Math.abs(point.value) / maxVal) * 100));

                const colWrapper = document.createElement('div');
                colWrapper.className = 'flex-1 flex flex-col items-center h-full justify-end cursor-pointer group relative';
                
                // Activeer hover feedback op de balk
                colWrapper.onmouseenter = () => {
                    const statusText = isPositive ? 'Winst' : 'Kosten';
                    const valueFormatted = Math.abs(point.value).toLocaleString('nl-NL');
                    document.getElementById('chart-hover-info').innerHTML = `
                        <span class="text-xs text-slate-400 font-bold">Jaar ${point.year}: </span>
                        <span class="text-sm font-mono font-black ${isPositive ? 'text-emerald-400' : 'text-rose-400'}">
                            ${isPositive ? '+' : '-'}€ ${valueFormatted} ${statusText}
                        </span>`;
                };
                colWrapper.onmouseleave = () => {
                    document.getElementById('chart-hover-info').innerHTML = 'Beweeg over de kolommen om de cumulatieve winst te bekijken';
                };

                const bar = document.createElement('div');
                bar.className = `w-1.5 md:w-2.5 rounded-full transition-all duration-300 ${
                    isPositive ? 'bg-emerald-500 hover:bg-emerald-400' : 'bg-rose-500 hover:bg-rose-400'
                }`;
                bar.style.height = `${barHeight}%`;

                const label = document.createElement('span');
                label.className = 'text-[9px] text-slate-500 mt-2 font-mono';
                label.innerText = point.year;

                colWrapper.appendChild(bar);
                colWrapper.appendChild(label);
                container.appendChild(colWrapper);
            });
        }

        // Wizard interactieve stappen-regelaar
        function updateStep() {
            // Update step header
            document.getElementById('current-step-num').innerText = step;
            document.getElementById('current-step-title').innerText = stepTitles[step];
            document.getElementById('step-progress-bar').style.width = `${(step / 6) * 100}%`;

            // Verberg alle stappen en toon de huidige
            document.querySelectorAll('.step-container').forEach(el => el.classList.add('hidden'));
            document.getElementById(`step-${step}`).classList.remove('hidden');

            // Beheer navigatie knoppen visibility
            const nav = document.getElementById('wizard-navigation');
            const btnPrev = document.getElementById('btn-prev');
            const btnNext = document.getElementById('btn-next');

            if (step === 1) {
                btnPrev.classList.add('opacity-30', 'cursor-not-allowed');
                btnPrev.disabled = true;
            } else {
                btnPrev.classList.remove('opacity-30', 'cursor-not-allowed');
                btnPrev.disabled = false;
            }

            if (step >= 5) {
                nav.classList.add('hidden');
            } else {
                nav.classList.remove('hidden');
            }
        }

        function nextStep() {
            if (validateStep(step)) {
                if (step < 6) {
                    step++;
                    updateStep();
                }
            }
        }

        function prevStep() {
            if (step > 1) {
                step--;
                updateStep();
            }
        }

        // Invoer validatie
        function validateStep(currentStep) {
            let isValid = true;
            
            // Reset foutmeldingen
            document.querySelectorAll('[id^="error-"]').forEach(el => {
                el.innerText = '';
                el.classList.add('hidden');
            });
            document.querySelectorAll('input').forEach(el => el.classList.remove('border-red-500'));

            if (currentStep === 5) {
                const fields = ['firstName', 'lastName', 'email', 'phone', 'postalCode', 'houseNumber'];
                fields.forEach(f => {
                    const input = document.getElementById(f);
                    const error = document.getElementById(`error-${f}`);
                    if (!input.value.trim()) {
                        error.innerText = 'Dit veld is verplicht.';
                        error.classList.remove('hidden');
                        input.classList.add('border-red-500');
                        isValid = false;
                    }
                });

                // E-mail validatie
                const emailInput = document.getElementById('email');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (emailInput.value && !emailRegex.test(emailInput.value)) {
                    const error = document.getElementById('error-email');
                    error.innerText = 'Vul een geldig e-mailadres in.';
                    error.classList.remove('hidden');
                    emailInput.classList.add('border-red-500');
                    isValid = false;
                }

                // Postcode validatie
                const postalInput = document.getElementById('postalCode');
                const postalRegex = /^[1-9][0-9]{3}\s?[a-zA-Z]{2}$/;
                if (postalInput.value && !postalRegex.test(postalInput.value.replace(/\s+/g, ''))) {
                    const error = document.getElementById('error-postalCode');
                    error.innerText = 'Ongeldige postcode (bijv. 1234 AB).';
                    error.classList.remove('hidden');
                    postalInput.classList.add('border-red-500');
                    isValid = false;
                }

                // Akkoord voorwaarden checken
                const agree = document.getElementById('agreeTerms');
                if (!agree.checked) {
                    const error = document.getElementById('error-agreeTerms');
                    error.innerText = 'U dient akkoord te gaan met de voorwaarden.';
                    error.classList.remove('hidden');
                    isValid = false;
                }
            }
            return isValid;
        }

        // Selectors setters & UI binders
        function setProjectType(type) {
            formData.projectType = type;
            document.querySelectorAll('.project-card').forEach(el => {
                el.classList.remove('border-emerald-500', 'bg-emerald-500/10', 'shadow-lg', 'shadow-emerald-500/5');
                el.classList.add('border-slate-800', 'bg-slate-900/60');
                el.querySelector('.project-icon-wrapper').classList.remove('bg-emerald-500', 'text-slate-950');
                el.querySelector('.project-icon-wrapper').classList.add('bg-slate-800', 'text-slate-300');
                el.querySelector('.check-badge').classList.add('hidden');
            });

            const activeCard = document.getElementById(`card-project-${type}`);
            activeCard.classList.add('border-emerald-500', 'bg-emerald-500/10', 'shadow-lg', 'shadow-emerald-500/5');
            activeCard.classList.remove('border-slate-800', 'bg-slate-900/60');
            activeCard.querySelector('.project-icon-wrapper').classList.add('bg-emerald-500', 'text-slate-950');
            activeCard.querySelector('.project-icon-wrapper').classList.remove('bg-slate-800', 'text-slate-300');
            activeCard.querySelector('.check-badge').classList.remove('hidden');

            runCalculations();
        }

        function setRoofType(type) {
            formData.roofType = type;
            document.querySelectorAll('.roof-btn').forEach(btn => {
                btn.className = 'roof-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-slate-800 bg-slate-900/40 text-slate-300 hover:border-slate-700';
            });
            document.getElementById(`btn-roof-${type}`).className = 'roof-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-emerald-500 bg-emerald-500/10 text-emerald-400';
            runCalculations();
        }

        function setRoofMaterial(mat) {
            formData.roofMaterial = mat;
            document.querySelectorAll('.mat-btn').forEach(btn => {
                btn.className = 'mat-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-slate-800 bg-slate-900/40 text-slate-300 hover:border-slate-700';
            });
            document.getElementById(`btn-mat-${mat}`).className = 'mat-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-emerald-500 bg-emerald-500/10 text-emerald-400';
            runCalculations();
        }

        function setOrientation(dir) {
            formData.roofOrientation = dir;
            document.querySelectorAll('.ori-btn').forEach(btn => {
                btn.className = 'ori-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-slate-800 bg-slate-900/40 text-slate-300 hover:border-slate-700';
            });
            document.getElementById(`btn-ori-${dir}`).className = 'ori-btn py-3 px-2 rounded-xl border text-center text-xs font-bold transition-all border-emerald-500 bg-emerald-500/10 text-emerald-400';
            runCalculations();
        }

        // Slider binders
        function updateAreaInput(val) {
            document.getElementById('input-area').value = val;
            formData.area = parseInt(val);
            runCalculations();
        }
        function updateAreaSlider(val) {
            document.getElementById('slider-area').value = val;
            formData.area = parseInt(val) || 0;
            runCalculations();
        }

        function updateElecInput(val) {
            document.getElementById('input-elec').value = val;
            formData.energyConsumption = parseInt(val);
            runCalculations();
        }
        function updateElecSlider(val) {
            document.getElementById('slider-elec').value = val;
            formData.energyConsumption = parseInt(val) || 0;
            runCalculations();
        }

        function updateGasInput(val) {
            document.getElementById('input-gas').value = val;
            formData.gasConsumption = parseInt(val);
            runCalculations();
        }
        function updateGasSlider(val) {
            document.getElementById('slider-gas').value = val;
            formData.gasConsumption = parseInt(val) || 0;
            runCalculations();
        }

        function updateRates() {
            formData.electricityRate = parseFloat(document.getElementById('rate-elec').value) || 0;
            formData.gasRate = parseFloat(document.getElementById('rate-gas').value) || 0;
            runCalculations();
        }

        function toggleAddon(addon) {
            formData[addon] = !formData[addon];
            const card = document.getElementById(`addon-${addon}`);
            const icon = card.querySelector('.addon-icon');

            if (formData[addon]) {
                card.classList.remove('border-slate-800', 'bg-slate-900/60');
                card.classList.add('border-emerald-500', 'bg-emerald-500/10');
                icon.classList.remove('bg-slate-800', 'text-slate-300');
                icon.classList.add('bg-emerald-500', 'text-slate-950');
            } else {
                card.classList.add('border-slate-800', 'bg-slate-900/60');
                card.classList.remove('border-emerald-500', 'bg-emerald-500/10');
                icon.classList.add('bg-slate-800', 'text-slate-300');
                icon.classList.remove('bg-emerald-500', 'text-slate-950');
            }
            runCalculations();
        }

        // Submit form en koppel met PHP Webhook
        function handleFormSubmit(e) {
            e.preventDefault();
            if (!validateStep(5)) return;

            const submitBtn = document.getElementById('btn-submit-form');
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg class="w-5 h-5 animate-spin mr-2 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                <span>Verzenden naar PHP CRM...</span>
            `;

            // Vul formuliergegevens in de state
            formData.firstName = document.getElementById('firstName').value;
            formData.lastName = document.getElementById('lastName').value;
            formData.email = document.getElementById('email').value;
            formData.phone = document.getElementById('phone').value;
            formData.postalCode = document.getElementById('postalCode').value;
            formData.houseNumber = document.getElementById('houseNumber').value;

            const payload = {
                event_type: "lead_generation_quote_php",
                source: "PHP Slimme Offerte-tool",
                contact: {
                    first_name: formData.firstName,
                    last_name: formData.lastName,
                    email: formData.email,
                    phone: formData.phone,
                    address: {
                        postal_code: formData.postalCode.toUpperCase(),
                        house_number: formData.houseNumber,
                        country: "Nederland"
                    }
                },
                specifications: {
                    desired_solution: formData.projectType,
                    roof_type: formData.roofType,
                    roof_material: formData.roofMaterial,
                    roof_orientation: formData.roofOrientation,
                    surface_area_m2: formData.area,
                    historical_metrics: {
                        electricity_consumption_kwh: formData.energyConsumption,
                        gas_consumption_m3: formData.gasConsumption,
                        current_electricity_rate: formData.electricityRate,
                        current_gas_rate: formData.gasRate
                    },
                    addons: {
                        home_battery: formData.hasBattery,
                        ev_charger: formData.hasCharger,
                        premium_optimized_inverter: formData.premiumInverter,
                        smart_automation_hub: formData.smartAutomation
                    }
                },
                proposals: {
                    gross_pricing_eur: calculatedValues.basePrice,
                    subsidies_applicable_eur: calculatedValues.subsidy,
                    net_investment_required_eur: calculatedValues.netInvestment,
                    first_year_calculated_savings_eur: calculatedValues.yearlySavings,
                    estimated_payback_years: parseFloat(calculatedValues.paybackPeriod),
                    annual_co2_reduction_kg: calculatedValues.co2Savings,
                    trees_planted_equivalent: calculatedValues.treesEquivalent,
                    guaranteed_install_weeks: calculatedValues.installWeeks
                }
            };

            // AJAX call naar hetzelfde PHP bestand om de lead server-side te registreren
            fetch('?action=submit_lead', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Injecteer de log direct bovenaan de logs
                    const logsContainer = document.getElementById('crm-logs-list');
                    
                    // Verwijder lege log placeholder indien aanwezig
                    if (logsContainer.innerHTML.includes('[Wachten op lead-activatie...]')) {
                        logsContainer.innerHTML = '';
                    }

                    const rawLogHtml = `
                        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl overflow-hidden font-mono text-xs shadow-2xl">
                            <div class="bg-slate-850 px-4 py-2.5 flex items-center justify-between border-b border-slate-800 text-slate-300">
                                <span class="flex items-center text-emerald-400 font-bold">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>
                                    ${data.log.event}
                                </span>
                                <span class="text-[10px] text-slate-500">${data.log.time} | STATUS: ${data.log.status}</span>
                            </div>
                            <pre class="p-4 text-emerald-400/90 bg-slate-950 overflow-x-auto leading-relaxed max-h-72 selection:bg-slate-800"><code>${data.log.payload}</code></pre>
                        </div>
                    `;
                    logsContainer.insertAdjacentHTML('afterbegin', rawLogHtml);

                    // Update succes view
                    document.getElementById('success-first-name').innerText = formData.firstName;
                    step = 6;
                    updateStep();

                    if (window.confetti) {
                        window.confetti({
                            particleCount: 140,
                            spread: 90,
                            origin: { y: 0.6 }
                        });
                    }
                } else {
                    alert('Fout tijdens indienen van de offerte: ' + data.error);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Netwerkfout bij indienen.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `
                    <span>Mijn Offerte-Rapportage Aanvragen</span>
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                `;
            });
        }

        // Genereer de offerte PDF in de browser
        function generatePDF() {
            if (!window.jspdf) {
                alert("PDF-bibliotheek laadt nog, een moment geduld.");
                return;
            }

            const pdfBtnText = document.getElementById('pdf-btn-text');
            pdfBtnText.innerText = 'PDF genereren...';

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });

            const primaryColor = [16, 185, 129];
            const darkSlate = [15, 23, 42];
            const lightGrey = [248, 250, 252];

            // Header Background
            doc.setFillColor(...darkSlate);
            doc.rect(0, 0, 210, 50, 'F');

            // Corporate Title
            doc.setTextColor(255, 255, 255);
            doc.setFont("helvetica", "bold");
            doc.setFontSize(24);
            doc.text("ECO-VERDUURZAMINGSADVIES", 15, 22);
            
            doc.setFont("helvetica", "normal");
            doc.setFontSize(10);
            doc.setTextColor(156, 163, 175);
            doc.text("Gepersonaliseerd Technisch & Financieel Adviesrapport (PHP Backend)", 15, 30);
            doc.text(`Aanmaakdatum: ${new Date().toLocaleDateString('nl-NL')}`, 155, 22);
            doc.text("Kenmerk: PHP-ECO-OFFERTE", 155, 30);

            // Green divider
            doc.setFillColor(...primaryColor);
            doc.rect(0, 50, 210, 2, 'F');

            // 1. Client & Property Section
            doc.setTextColor(...darkSlate);
            doc.setFontSize(14);
            doc.setFont("helvetica", "bold");
            doc.text("1. Klant- & Locatiegegevens", 15, 68);

            doc.setDrawColor(226, 232, 240);
            doc.setLineWidth(0.5);
            doc.line(15, 71, 195, 71);

            doc.setFontSize(10);
            doc.setFont("helvetica", "normal");
            doc.setTextColor(71, 85, 105);
            
            // Left Grid
            doc.text(`Naam: ${formData.firstName} ${formData.lastName}`, 15, 80);
            doc.text(`E-mailadres: ${formData.email}`, 15, 87);
            doc.text(`Telefoonnummer: ${formData.phone}`, 15, 94);
            
            // Right Grid
            doc.text(`Locatie: ${formData.postalCode.toUpperCase()}, Huisnr: ${formData.houseNumber}`, 110, 80);
            doc.text(`Daktype: ${formData.roofType.toUpperCase()} (${formData.roofMaterial})`, 110, 87);
            doc.text(`Oriëntatie: ${formData.roofOrientation.toUpperCase()}`, 110, 94);

            // 2. Financials Section
            doc.setFillColor(...lightGrey);
            doc.roundedRect(15, 106, 180, 55, 3, 3, 'F');

            doc.setFont("helvetica", "bold");
            doc.setFontSize(12);
            doc.setTextColor(...darkSlate);
            doc.text("2. Financiële Investering & Subsidies", 22, 116);

            doc.setDrawColor(203, 213, 225);
            doc.line(22, 120, 188, 120);

            doc.setFont("helvetica", "normal");
            doc.setFontSize(10);
            doc.setTextColor(71, 85, 105);
            doc.text("Bruto investeringsindicatie:", 22, 128);
            doc.text(`€ ${calculatedValues.basePrice.toLocaleString('nl-NL')}`, 145, 128, { align: 'right' });

            doc.text("Subsidieteruggave / ISDE Regeling:", 22, 135);
            doc.setTextColor(16, 185, 129);
            doc.text(`- € ${calculatedValues.subsidy.toLocaleString('nl-NL')}`, 145, 135, { align: 'right' });

            doc.setTextColor(...darkSlate);
            doc.line(22, 140, 188, 140);

            doc.setFont("helvetica", "bold");
            doc.text("Netto investering (Inclusief 0% BTW-regeling):", 22, 147);
            doc.text(`€ ${calculatedValues.netInvestment.toLocaleString('nl-NL')}`, 145, 147, { align: 'right' });

            // 3. Efficiency Outcomes
            doc.setTextColor(...darkSlate);
            doc.setFontSize(14);
            doc.setFont("helvetica", "bold");
            doc.text("3. Prognose & Terugverdienmodel", 15, 178);
            
            doc.setDrawColor(226, 232, 240);
            doc.line(15, 181, 195, 181);

            doc.setFont("helvetica", "normal");
            doc.setFontSize(10);
            doc.setTextColor(71, 85, 105);
            
            doc.text("Jaarlijkse besparing op uw energielasten:", 15, 190);
            doc.setFont("helvetica", "bold");
            doc.setTextColor(16, 185, 129);
            doc.text(`€ ${calculatedValues.yearlySavings.toLocaleString('nl-NL')} per jaar`, 110, 190);

            doc.setFont("helvetica", "normal");
            doc.setTextColor(71, 85, 105);
            doc.text("Geraamde terugverdientijd van dit project:", 15, 197);
            doc.setFont("helvetica", "bold");
            doc.setTextColor(...darkSlate);
            doc.text(`${calculatedValues.paybackPeriod} Jaar`, 110, 197);

            doc.setFont("helvetica", "normal");
            doc.setTextColor(71, 85, 105);
            doc.text("Milieu-impact (Jaarlijkse CO2-reductie):", 15, 204);
            doc.setFont("helvetica", "bold");
            doc.setTextColor(5, 150, 105);
            doc.text(`${calculatedValues.co2Savings} KG CO2 (~ equivalent van ${calculatedValues.treesEquivalent} bomen)`, 110, 204);

            // Upgrades listing
            doc.setFont("helvetica", "bold");
            doc.setFontSize(10);
            doc.setTextColor(...darkSlate);
            doc.text("Geselecteerde Opties & Systeemupgrades:", 15, 218);
            
            doc.setFont("helvetica", "normal");
            doc.setTextColor(100, 116, 139);
            let optY = 225;
            if (formData.hasBattery) { doc.text("-> Hoogwaardig Thuisbatterijsysteem voor opslag van overschotten", 20, optY); optY += 6; }
            if (formData.hasCharger) { doc.text("-> Geïntegreerde slimme EV-laadpaal voor elektrisch laden op eigen stroom", 20, optY); optY += 6; }
            if (formData.premiumInverter) { doc.text("-> Premium micro-optimizers en omvormersysteem voor schaduwcompensatie", 20, optY); optY += 6; }
            if (formData.smartAutomation) { doc.text("-> Smart Home Energie Manager voor dynamische stroomhandel", 20, optY); optY += 6; }
            if (!formData.hasBattery && !formData.hasCharger && !formData.premiumInverter && !formData.smartAutomation) {
                doc.text("-> Geen extra upgrades geselecteerd (standaardopstelling)", 20, optY);
            }

            // Disclaimer
            doc.setFillColor(...darkSlate);
            doc.rect(0, 272, 210, 25, 'F');
            doc.setTextColor(156, 163, 175);
            doc.setFontSize(7.5);
            doc.setFont("helvetica", "italic");
            doc.text("Garantieverklaring: Dit betreft een geavanceerde, gepersonaliseerde offerte-indicatie. Na akkoord komt een gecertificeerd", 15, 280);
            doc.text("installateur bij u op locatie langs voor de definitieve inmeting en technische keuring van de dakconstructie.", 15, 284);

            doc.save(`EcoVerduurzamingsOfferte_${formData.lastName}.pdf`);
            pdfBtnText.innerText = 'Download Offerte PDF';
        }

        function resetWizard() {
            step = 1;
            updateStep();
            
            // Reset contact velden
            document.getElementById('firstName').value = '';
            document.getElementById('lastName').value = '';
            document.getElementById('email').value = '';
            document.getElementById('phone').value = '';
            document.getElementById('postalCode').value = '';
            document.getElementById('houseNumber').value = '';
            document.getElementById('agreeTerms').checked = false;

            // Reset addon classes
            ['hasBattery', 'hasCharger', 'premiumInverter', 'smartAutomation'].forEach(addon => {
                formData[addon] = false;
                const card = document.getElementById(`addon-${addon}`);
                const icon = card.querySelector('.addon-icon');
                card.classList.add('border-slate-800', 'bg-slate-900/60');
                card.classList.remove('border-emerald-500', 'bg-emerald-500/10');
                icon.classList.add('bg-slate-800', 'text-slate-300');
                icon.classList.remove('bg-emerald-500', 'text-slate-950');
            });

            setProjectType('solar');
            setRoofType('schuin');
            setRoofMaterial('pannen');
            setOrientation('zuid');
            
            // Reset sliders
            document.getElementById('slider-area').value = 45;
            document.getElementById('input-area').value = 45;
            formData.area = 45;

            document.getElementById('slider-elec').value = 3500;
            document.getElementById('input-elec').value = 3500;
            formData.energyConsumption = 3500;

            document.getElementById('slider-gas').value = 1200;
            document.getElementById('input-gas').value = 1200;
            formData.gasConsumption = 1200;

            runCalculations();
        }

        // Initialize App on load
        window.onload = function() {
            runCalculations();
            updateStep();
        };
    </script>
</body>
</html>