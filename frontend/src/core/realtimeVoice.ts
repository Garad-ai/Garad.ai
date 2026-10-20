export type RealtimeServerEvent = {
  type?: string
  delta?: string
  transcript?: string
  error?: { message?: string }
  response?: {
    status?: string
    status_details?: { reason?: string }
  }
  [key: string]: unknown
}

export class RealtimeVoiceConnection {
  private readonly socket: WebSocket

  readonly ready: Promise<void>

  constructor(
    ephemeralKey: string,
    model: string,
    onMessage: (message: RealtimeServerEvent) => void,
    onClose: () => void,
    onError: (error: Error) => void,
  ) {
    const endpoint = new URL("wss://api.openai.com/v1/realtime")
    endpoint.searchParams.set("model", model)
    this.socket = new WebSocket(endpoint.toString(), [
      "realtime",
      `openai-insecure-api-key.${ephemeralKey}`,
    ])

    this.ready = new Promise<void>((resolve, reject) => {
      let opened = false
      this.socket.addEventListener("open", () => {
        opened = true
        resolve()
      })
      this.socket.addEventListener("error", () => {
        const error = new Error("OpenAI Realtime connection failed.")
        if (!opened) reject(error)
        else onError(error)
      })
      this.socket.addEventListener("close", () => {
        if (opened) onClose()
      })
    })

    this.socket.addEventListener("message", (event) => {
      try {
        const message = JSON.parse(String(event.data)) as RealtimeServerEvent
        onMessage(message)
      } catch (error) {
        onError(
          error instanceof Error
            ? error
            : new Error("OpenAI Realtime sent an invalid event."),
        )
      }
    })
  }

  async send(message: Record<string, unknown>): Promise<void> {
    await this.ready
    if (this.socket.readyState !== WebSocket.OPEN) {
      throw new Error("OpenAI Realtime connection is not open.")
    }
    this.socket.send(JSON.stringify(message))
  }

  close(): void {
    if (
      this.socket.readyState === WebSocket.CONNECTING ||
      this.socket.readyState === WebSocket.OPEN
    ) {
      this.socket.close()
    }
  }
}

export class RealtimePcmCapture {
  readonly audioContext: AudioContext

  private source: MediaStreamAudioSourceNode | null = null

  private worklet: AudioWorkletNode | null = null

  private pendingSamples = new Int16Array(0)

  constructor() {
    this.audioContext = new AudioContext({ sampleRate: 24000 })
  }

  async start(
    stream: MediaStream,
    workletUrl: string,
    onAudio: (base64Audio: string) => void,
  ): Promise<void> {
    await this.audioContext.resume()
    await this.audioContext.audioWorklet.addModule(workletUrl)
    this.source = this.audioContext.createMediaStreamSource(stream)
    this.worklet = new AudioWorkletNode(
      this.audioContext,
      "audio-worklet-processor",
    )
    this.worklet.port.onmessage = (event: MessageEvent<ArrayBuffer>) => {
      const samples = new Int16Array(event.data)
      const combined = new Int16Array(
        this.pendingSamples.length + samples.length,
      )
      combined.set(this.pendingSamples)
      combined.set(samples, this.pendingSamples.length)

      const packetSize = 1200 // smaller packets so server/client can start processing earlier
      let offset = 0
      while (combined.length - offset >= packetSize) {
        const packet = combined.slice(offset, offset + packetSize)
        offset += packetSize
        const bytes = new Uint8Array(
          packet.buffer,
          packet.byteOffset,
          packet.byteLength,
        )
        let binary = ""
        for (let index = 0; index < bytes.length; index += 1) {
          binary += String.fromCharCode(bytes[index])
        }
        onAudio(btoa(binary))
      }

      this.pendingSamples = combined.slice(offset)
    }
    this.source.connect(this.worklet)
  }

  close(): void {
    if (this.worklet) {
      this.worklet.port.onmessage = null
      this.worklet.disconnect()
      this.worklet = null
    }
    this.source?.disconnect()
    this.source = null
    this.pendingSamples = new Int16Array(0)
    if (this.audioContext.state !== "closed") {
      void this.audioContext.close()
    }
  }
}

export class RealtimePcmPlayer {
  readonly audioContext: AudioContext

  // Diagnostics/callbacks
  onDrained: (() => void) | null = null
  onAudioOutputStarted: ((metrics: { rms: number; peak: number }) => void) | null =
    null
  onAudioDataReceived:
    | ((metrics: { samples: number; rms: number; peak: number }) => void)
    | null = null

  // allow a temporary gain multiplier for debugging/low-volume issues
  gainMultiplier = 1.0

  private audioPending = false
  private reportedAudioData = false
  private reportedAudioOutput = false
  private scheduledUntil = 0
  private readonly sources = new Set<AudioBufferSourceNode>()

  constructor() {
    this.audioContext = new AudioContext({ sampleRate: 24000 })
  }

  async start(): Promise<void> {
    await this.audioContext.resume()
  }

  play(base64Audio: string): void {
    const binary = atob(base64Audio)
    const bytes = Uint8Array.from(binary, (character) =>
      character.charCodeAt(0),
    )
    const audio = new Int16Array(
      bytes.buffer,
      bytes.byteOffset,
      Math.floor(bytes.byteLength / Int16Array.BYTES_PER_ELEMENT),
    )
    if (audio.length > 0) {
      this.audioPending = true
      if (!this.reportedAudioData) {
        let sumSquares = 0
        let peak = 0
        for (const sample of audio) {
          const normalized = sample / 32768
          sumSquares += normalized * normalized
          peak = Math.max(peak, Math.abs(normalized))
        }
        this.onAudioDataReceived?.({
          samples: audio.length,
          rms: Math.sqrt(sumSquares / audio.length),
          peak,
        })
        this.reportedAudioData = true
      }
    }
    if (audio.length === 0) return

    // Convert Int16 -> Float32 and apply optional gain multiplier for debugging
    const samples = new Float32Array(audio.length)
    let sumSquares = 0
    let peak = 0
    for (let index = 0; index < audio.length; index += 1) {
      const sample = (audio[index] / 32768) * this.gainMultiplier
      samples[index] = sample
      sumSquares += sample * sample
      peak = Math.max(peak, Math.abs(sample))
    }

    const buffer = this.audioContext.createBuffer(
      1,
      samples.length,
      this.audioContext.sampleRate,
    )
    buffer.copyToChannel(samples, 0)

    const source = this.audioContext.createBufferSource()
    source.buffer = buffer
    source.connect(this.audioContext.destination)

    // schedule with a very small minimum latency to reduce perceived reply delay
    const startAt = Math.max(
      this.audioContext.currentTime + 0.005, // 5ms
      this.scheduledUntil,
    )
    this.scheduledUntil = startAt + buffer.duration
    this.sources.add(source)
    source.onended = () => {
      this.sources.delete(source)
      if (this.sources.size !== 0) return
      this.audioPending = false
      this.scheduledUntil = this.audioContext.currentTime
      this.onDrained?.()
    }
    source.start(startAt)
    if (!this.reportedAudioOutput) {
      this.onAudioOutputStarted?.({
        rms: Math.sqrt(sumSquares / samples.length),
        peak,
      })
      this.reportedAudioOutput = true
    }
  }

  clear(): void {
    this.audioPending = false
    this.reportedAudioData = false
    this.reportedAudioOutput = false
    this.scheduledUntil = this.audioContext.currentTime
    this.sources.forEach((source) => {
      source.onended = null
      try {
        source.stop()
      } catch {}
      source.disconnect()
    })
    this.sources.clear()
  }

  get hasPendingAudio(): boolean {
    return this.audioPending
  }

  close(): void {
    this.clear()
    if (this.audioContext.state !== "closed") {
      void this.audioContext.close()
    }
  }
}
