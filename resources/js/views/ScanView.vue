<template>
    <div class="min-h-screen bg-gray-900 flex flex-col items-center justify-center p-4">
        <!-- Header -->
        <div class="w-full max-w-md mb-6 flex items-center justify-between">
            <RouterLink to="/" class="text-gray-400 hover:text-white text-sm">← Back</RouterLink>
            <h1 class="text-white font-semibold">Scan Device</h1>
            <div class="w-16"></div>
        </div>

        <!-- Scanner area -->
        <div class="w-full max-w-md">
            <div v-if="!scanning && !result" class="text-center">
                <div class="bg-gray-800 rounded-xl p-8 mb-4">
                    <div class="text-6xl mb-4">📷</div>
                    <p class="text-gray-300 mb-6">Scan a device QR code or barcode to look up its details instantly.</p>
                    <button @click="startScanning"
                        class="w-full py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        Start Camera
                    </button>
                </div>
                <!-- Manual entry fallback -->
                <div class="bg-gray-800 rounded-xl p-4">
                    <p class="text-gray-400 text-sm mb-2">Or enter asset tag manually:</p>
                    <div class="flex gap-2">
                        <input v-model="manualTag" type="text" placeholder="e.g. MEA-000001"
                            class="flex-1 px-3 py-2 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @keyup.enter="lookupManual" />
                        <button @click="lookupManual"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Go
                        </button>
                    </div>
                    <p v-if="manualError" class="text-red-400 text-sm mt-2">{{ manualError }}</p>
                </div>
            </div>

            <!-- Camera view -->
            <div v-if="scanning" class="relative">
                <div class="relative bg-black rounded-xl overflow-hidden" style="aspect-ratio: 1;">
                    <video ref="videoEl" class="w-full h-full object-cover" playsinline autoplay></video>
                    <!-- Scanning overlay -->
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-48 h-48 border-2 border-blue-400 rounded-lg relative">
                            <div class="absolute top-0 left-0 w-6 h-6 border-t-4 border-l-4 border-blue-400 rounded-tl"></div>
                            <div class="absolute top-0 right-0 w-6 h-6 border-t-4 border-r-4 border-blue-400 rounded-tr"></div>
                            <div class="absolute bottom-0 left-0 w-6 h-6 border-b-4 border-l-4 border-blue-400 rounded-bl"></div>
                            <div class="absolute bottom-0 right-0 w-6 h-6 border-b-4 border-r-4 border-blue-400 rounded-br"></div>
                        </div>
                    </div>
                    <div class="absolute bottom-4 left-0 right-0 text-center">
                        <p class="text-white text-sm bg-black/50 inline-block px-3 py-1 rounded-full">
                            {{ scanStatus }}
                        </p>
                    </div>
                </div>
                <button @click="stopScanning"
                    class="w-full mt-4 py-3 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-colors">
                    Cancel
                </button>
            </div>

            <!-- Result -->
            <div v-if="result" class="bg-gray-800 rounded-xl p-6 text-center">
                <div class="text-4xl mb-3">✅</div>
                <p class="text-green-400 font-medium mb-1">Device Found!</p>
                <p class="text-white font-mono text-lg mb-1">{{ result.asset_tag }}</p>
                <p class="text-gray-400 text-sm mb-4">{{ result.manufacturer }} {{ result.model }}</p>
                <div class="flex gap-3">
                    <button @click="resetScanner"
                        class="flex-1 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-700">
                        Scan Another
                    </button>
                    <RouterLink :to="{ name: 'device-detail', params: { id: result.id }}"
                        class="flex-1 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-center">
                        View Device
                    </RouterLink>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { BrowserMultiFormatReader } from '@zxing/library'
import api from '../api.js'

const router     = useRouter()
const videoEl    = ref(null)
const scanning   = ref(false)
const result     = ref(null)
const scanStatus = ref('Point camera at QR code or barcode...')
const manualTag  = ref('')
const manualError = ref(null)

let codeReader = null
let stream     = null

async function startScanning() {
    scanning.value   = true
    scanStatus.value = 'Starting camera...'

    try {
        codeReader = new BrowserMultiFormatReader()

        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' } // Use back camera on mobile
        })

        videoEl.value.srcObject = stream
        scanStatus.value = 'Point camera at QR code or barcode...'

        // Start decoding
        codeReader.decodeFromStream(stream, videoEl.value, async (result, error) => {
            if (result) {
                const text = result.getText()
                scanStatus.value = `Detected: ${text}`
                await lookupDevice(text)
            }
        })
    } catch (e) {
        scanStatus.value = 'Camera access denied. Use manual entry below.'
        scanning.value   = false
        console.error('Camera error:', e)
    }
}

function stopScanning() {
    cleanup()
    scanning.value = false
}

async function lookupDevice(assetTag) {
    try {
        stopScanning()
        const response = await api.get('/devices/find-by-asset-tag', {
            params: { asset_tag: assetTag }
        })
        result.value = response.data
    } catch (e) {
        scanStatus.value = `Device "${assetTag}" not found. Try again.`
        scanning.value   = false
    }
}

async function lookupManual() {
    manualError.value = null
    if (!manualTag.value) return
    try {
        const response = await api.get('/devices/find-by-asset-tag', {
            params: { asset_tag: manualTag.value.trim().toUpperCase() }
        })
        result.value = response.data
    } catch (e) {
        manualError.value = `Device "${manualTag.value}" not found.`
    }
}

function resetScanner() {
    result.value    = null
    manualTag.value = ''
    manualError.value = null
}

function cleanup() {
    if (codeReader) {
        codeReader.reset()
        codeReader = null
    }
    if (stream) {
        stream.getTracks().forEach(track => track.stop())
        stream = null
    }
}

onUnmounted(cleanup)
</script>
