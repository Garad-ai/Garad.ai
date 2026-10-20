import { useCallback, useEffect, useMemo, useState } from "react"

import type { ApiClient, AudioMusicModel, AudioStudioItem } from "./core/api"

type Props = {
  api: ApiClient
  isAuthenticated: boolean
  onRequireLogin: () => void
  onBackToChat: () => void
  onNavigateTool: (tool: "image" | "video" | "audio" | "social") => void
  onUpgrade?: () => void
}

type AudioMode = "song" | "voiceover" | "speech"

type AudioTemplate = {
  id: string
  mode: AudioMode
  name: string
  category: string
  description: string
  text: string
}

const templates: AudioTemplate[] = [
  {
    id: "afro-pop",
    mode: "song",
    name: "Afro-pop chorus",
    category: "Music",
    description: "An upbeat Afro-pop song with warm vocals and an uplifting rhythm.",
    text: "Create an upbeat Afro-pop song with warm lead vocals, syncopated percussion, bright guitar, and an uplifting chorus about unity and new beginnings.",
  },
  {
    id: "brand-jingle",
    mode: "song",
    name: "Brand jingle",
    category: "Music",
    description: "A short, catchy brand jingle with a bright, friendly sound.",
    text: "Create a short, catchy brand jingle with a bright melody, friendly vocals, light percussion, and a polished modern pop sound.",
  },
  {
    id: "cinematic-score",
    mode: "song",
    name: "Cinematic score",
    category: "Music",
    description: "An emotional cinematic song that gradually builds to a powerful finale.",
    text: "Create an emotional cinematic song that begins softly with piano and strings, then builds into a powerful orchestral finale with expressive vocals.",
  },
  {
    id: "product-launch",
    mode: "voiceover",
    name: "Product launch",
    category: "Voice-over",
    description: "A clear, confident product introduction.",
    text: "Meet the new way to get more from your day. Thoughtfully designed, easy to use, and ready when you are. Discover what is possible.",
  },
  {
    id: "social-promo",
    mode: "voiceover",
    name: "Social promo",
    category: "Voice-over",
    description: "A concise social video narration.",
    text: "Big ideas deserve a brighter stage. Follow along, share what inspires you, and let’s make something remarkable together.",
  },
  {
    id: "documentary",
    mode: "voiceover",
    name: "Documentary narration",
    category: "Voice-over",
    description: "A warm, measured narration starter.",
    text: "Every change begins with a question. Here, a community is finding new answers through creativity, persistence, and a shared belief in what comes next.",
  },
  {
    id: "welcome",
    mode: "speech",
    name: "Welcome message",
    category: "Text to speech",
    description: "A friendly, natural welcome.",
    text: "Welcome. We’re glad you’re here. Take a look around, and let us know how we can help.",
  },
  {
    id: "announcement",
    mode: "speech",
    name: "Announcement",
    category: "Text to speech",
    description: "A short, polished announcement.",
    text: "Here’s an important update. Please take a moment to review the details, and contact our team if you have any questions.",
  },
  {
    id: "lesson",
    mode: "speech",
    name: "Lesson intro",
    category: "Text to speech",
    description: "An inviting introduction to a lesson.",
    text: "In this lesson, we’ll explore the key ideas step by step. By the end, you’ll have a practical way to put them into action.",
  },
]

const modeLabels: Record<AudioMode, string> = {
  song: "Music & songs",
  voiceover: "Voice-over",
  speech: "Text to speech",
}

const modeDescriptions: Record<AudioMode, string> = {
  song: "Describe the music you want. Garad will generate a song from your prompt.",
  voiceover: "Turn a script into narration for videos, ads, and presentations.",
  speech: "Convert written text into clear spoken audio.",
}

const formatDate = (value: string | null) =>
  value ? new Date(value).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" }) : "Recently generated"

export default function AudioGenerator({
  api,
  isAuthenticated,
  onRequireLogin,
  onBackToChat,
  onNavigateTool,
  onUpgrade,
}: Props) {
  const [mode, setMode] = useState<AudioMode>("song")
  const [title, setTitle] = useState("")
  const [text, setText] = useState("")
  const [voice, setVoice] = useState("alloy")
  const [voiceProvider, setVoiceProvider] = useState("openai")
  const [providerVoices, setProviderVoices] = useState<Array<{ id: string; name: string }>>([])
  const [speechModel, setSpeechModel] = useState<"tts-1" | "tts-1-hd">("tts-1")
  const [language, setLanguage] = useState("en-US")
  const [history, setHistory] = useState<AudioStudioItem[]>([])
  const [historyLoading, setHistoryLoading] = useState(false)
  const [generating, setGenerating] = useState(false)
  const [error, setError] = useState("")
  const [selectedTemplate, setSelectedTemplate] = useState("")
  const [activeAudio, setActiveAudio] = useState<AudioStudioItem | null>(null)
  const [view, setView] = useState<"creations" | "templates">("creations")
  const [musicModels, setMusicModels] = useState<AudioMusicModel[]>([])
  const [musicModelsLoading, setMusicModelsLoading] = useState(false)
  const [musicModelId, setMusicModelId] = useState("")
  const [musicStyle, setMusicStyle] = useState("")
  const [musicDuration, setMusicDuration] = useState(30)
  const [instrumental, setInstrumental] = useState(false)
  const [musicLyrics, setMusicLyrics] = useState("")
  const [outputFormat, setOutputFormat] = useState<"mp3" | "wav">("mp3")
  const [referenceVideo, setReferenceVideo] = useState<File | null>(null)
  const [referenceImages, setReferenceImages] = useState<File[]>([])
  const [advancedOpen, setAdvancedOpen] = useState(false)

  const loadHistory = useCallback(async () => {
    if (!isAuthenticated) {
      setHistory([])
      return
    }
    setHistoryLoading(true)
    try {
      setHistory(await api.getAudioHistory())
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Could not load your audio history.")
    } finally {
      setHistoryLoading(false)
    }
  }, [api, isAuthenticated])

  useEffect(() => {
    void loadHistory()
  }, [loadHistory])

  useEffect(() => {
    if (!isAuthenticated) return
    let current = true
    void api.getStudioVoiceOptions().then((options) => {
      if (!current) return
      setVoiceProvider(options.provider)
      setProviderVoices(options.voices)
      if (options.voices.length > 0) setVoice(options.voices[0].id)
      else if (options.provider !== "openai") setVoice("")
    }).catch((cause) => {
      if (!current) return
      setError(cause instanceof Error ? cause.message : "Could not load the configured voice options.")
    })
    return () => {
      current = false
    }
  }, [api, isAuthenticated])

  useEffect(() => {
    if (!isAuthenticated) return
    let current = true
    setMusicModelsLoading(true)
    void api.getStudioMusicModels().then((models) => {
      if (!current) return
      setMusicModels(models)
      setMusicModelId((selected) => models.some((model) => model.id === selected) ? selected : models[0]?.id ?? "")
    }).catch((cause) => {
      if (!current) return
      setError(cause instanceof Error ? cause.message : "Could not load Garad's available music models.")
    }).finally(() => {
      if (current) setMusicModelsLoading(false)
    })
    return () => {
      current = false
    }
  }, [api, isAuthenticated])

  const selectedMusicModel = musicModels.find((model) => model.id === musicModelId)
  const durationLabel = `${Math.floor(musicDuration / 60)}:${String(musicDuration % 60).padStart(2, "0")}`
  const videoEditorUrl = api.getStudioVideoEditorUrl()

  const modeTemplates = useMemo(() => templates.filter((template) => template.mode === mode), [mode])

  const selectMode = (next: AudioMode) => {
    setMode(next)
    setText("")
    setTitle("")
    setSelectedTemplate("")
    setError("")
  }

  const applyTemplate = (template: AudioTemplate) => {
    setText(template.text)
    setTitle(template.name)
    setSelectedTemplate(template.id)
    setError("")
  }

  const createAudio = async () => {
    if (!isAuthenticated) {
      onRequireLogin()
      return
    }
    if (!text.trim()) {
      setError(mode === "song" ? "Describe the song you want to generate." : "Enter the text you want to turn into audio.")
      return
    }
    if (mode !== "song" && !voice.trim()) {
      setError("Choose or enter a voice supported by Garad’s configured speech provider.")
      return
    }
    if (mode === "song" && (!selectedMusicModel || musicModelsLoading)) {
      setError(musicModelsLoading ? "Loading Garad's music models…" : "No music model is currently available.")
      return
    }
    if (mode === "song" && referenceVideo && referenceVideo.size > 50 * 1024 * 1024) {
      setError("The reference video must be 50 MB or smaller.")
      return
    }
    if (mode === "song" && referenceImages.length > 10) {
      setError("Choose up to 10 reference images.")
      return
    }
    if (mode === "song" && referenceVideo && referenceImages.length >= 10) {
      setError("Use at most 9 images when adding a video reference.")
      return
    }
    if (mode === "song" && referenceImages.some((image) => image.size > 25 * 1024 * 1024)) {
      setError("Each inspiration image must be 25 MB or smaller.")
      return
    }
    setGenerating(true)
    setError("")
    try {
      const result = mode === "song"
        ? await api.generateStudioMusic({
            prompt: text.trim(),
            title: title.trim() || "Generated song",
            model: selectedMusicModel!.id,
            style: musicStyle || undefined,
            duration: selectedMusicModel!.supportsDuration ? musicDuration : undefined,
            lyrics: selectedMusicModel!.supportsLyrics ? musicLyrics.trim() || undefined : undefined,
            instrumental,
            outputFormat,
            images: selectedMusicModel!.supportsImages ? referenceImages : [],
            video: selectedMusicModel!.supportsVideo ? referenceVideo : null,
          })
        : await api.generateStudioSpeech({
            content: text.trim(),
            voice,
            model: speechModel,
            language,
            title: title.trim() || (mode === "voiceover" ? "Voice-over" : "Text to speech"),
            kind: mode,
          })
      setHistory((current) => [result, ...current.filter((item) => item.id !== result.id)])
      setActiveAudio(result)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Audio generation failed. Please try again.")
    } finally {
      setGenerating(false)
    }
  }

  const deleteSong = async (item: AudioStudioItem) => {
    if (!item.recordId || !window.confirm("Delete this generated song?")) return
    try {
      await api.deleteStudioMusic(item.recordId, item.source)
      setHistory((current) => current.filter((row) => row.id !== item.id))
      setActiveAudio((current) => current?.id === item.id ? null : current)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Could not delete the song.")
    }
  }

  return (
    <section className="studio audio-studio" aria-label="Audio Studio">
      <aside className="sx-panel audio-studio-panel">
        <nav className="sx-tabs" role="tablist" aria-label="Creative tools">
          {(["image", "video", "audio"] as const).map((tool) => (
            <button key={tool} role="tab" aria-selected={tool === "audio"} className={tool === "audio" ? "active" : ""} onClick={() => tool !== "audio" && onNavigateTool(tool)} type="button">
              {tool === "image" ? "Image" : tool === "video" ? "Video" : "Audio"}
            </button>
          ))}
        </nav>

        <div className="sx-tool-card audio-studio-tool-card">
          <div><button className="sx-back" onClick={onBackToChat} type="button">‹ Chat</button><strong>Audio Studio</strong></div>
        </div>

        <label className="audio-studio-choice-label" htmlFor="audio-studio-choice">Choose audio tool</label>
        <div className="audio-studio-choice">
          <span aria-hidden="true">{mode === "song" ? "♫" : mode === "voiceover" ? "◖" : "▤"}</span>
          <select id="audio-studio-choice" value={mode} onChange={(event) => selectMode(event.target.value as AudioMode)}>
            {(Object.entries(modeLabels) as [AudioMode, string][]).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
          </select>
        </div>

        <section className="audio-studio-editor">
          <div className="audio-studio-subhead"><div><h3>{mode === "song" ? "Song details" : mode === "voiceover" ? "Voice-over script" : "Speech text"}</h3></div></div>
          <label className="audio-studio-label">Title<input maxLength={120} value={title} onChange={(event) => setTitle(event.target.value)} placeholder={mode === "song" ? "Name your song" : mode === "voiceover" ? "Name your voice-over" : "Name your audio"} /></label>
          <label className="audio-studio-label">{mode === "song" ? "Music prompt" : "Script"}
            <textarea rows={mode === "song" ? 7 : 6} maxLength={mode === "song" ? 2000 : 5000} value={text} onChange={(event) => setText(event.target.value)} placeholder={mode === "song" ? "Describe the song, sound, mood, and instruments you want…" : "Write or paste the words to speak…"} />
            <small className="audio-studio-count">{text.length} / {mode === "song" ? 2000 : 5000}</small>
          </label>
          {mode === "song" && (
            <>
              {musicModels.length === 0 && !musicModelsLoading && (
                <p className="audio-studio-model-note">No music provider is currently configured for this account.</p>
              )}
              <label className="audio-studio-label">Music model
                <select value={musicModelId} disabled={musicModelsLoading || musicModels.length === 0} onChange={(event) => {
                  const model = musicModels.find((item) => item.id === event.target.value)
                  setMusicModelId(event.target.value)
                  if (model && !model.outputFormats.includes(outputFormat)) setOutputFormat(model.outputFormats[0] ?? "mp3")
                  if (model && !model.supportsVideo) setReferenceVideo(null)
                  if (model && !model.supportsImages) setReferenceImages([])
                  if (model && !model.supportsLyrics) setMusicLyrics("")
                }}>
                  {musicModelsLoading && <option value="">Loading models…</option>}
                  {musicModels.map((model) => <option key={model.id} value={model.id}>{model.name}</option>)}
                </select>
                {selectedMusicModel?.description && <small>{selectedMusicModel.description}</small>}
              </label>

              {selectedMusicModel?.supportsVideo && (
                <label className="audio-reference-upload audio-reference-video">
                  <input type="file" accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.webm" onChange={(event) => setReferenceVideo(event.target.files?.[0] ?? null)} />
                  <span aria-hidden="true">▣</span><b>{referenceVideo ? referenceVideo.name : "Add a video reference"}</b>
                  <small>Garad uses a frame from your video as music inspiration · up to 50 MB</small>
                </label>
              )}

              {selectedMusicModel?.supportsDuration && (
                <label className="audio-studio-label">Duration · {durationLabel}
                  <input type="range" min="10" max="300" step="10" value={musicDuration} onChange={(event) => setMusicDuration(Number(event.target.value))} />
                  <span className="audio-duration-presets">
                    {[10, 30, 60, 120, 300].map((seconds) => <button type="button" key={seconds} className={musicDuration === seconds ? "active" : ""} onClick={() => setMusicDuration(seconds)}>{seconds < 60 ? `${seconds}s` : `${seconds / 60}m`}</button>)}
                  </span>
                </label>
              )}

              <label className="audio-instrumental-toggle">
                <input type="checkbox" checked={instrumental} onChange={(event) => setInstrumental(event.target.checked)} />
                <span><b>Instrumental only</b><small>Turn off to allow vocals on models that support them.</small></span>
              </label>

              <button type="button" className="audio-advanced-toggle" aria-expanded={advancedOpen} onClick={() => setAdvancedOpen((open) => !open)}>
                Advanced settings <span aria-hidden="true">{advancedOpen ? "⌃" : "⌄"}</span>
              </button>
              {advancedOpen && (
                <div className="audio-advanced-fields">
                  <label className="audio-studio-label">Style
                    <select value={musicStyle} onChange={(event) => setMusicStyle(event.target.value)}>
                      <option value="">Use prompt style</option>
                      {["Pop", "Lo-Fi", "Techno", "Jazz", "Rock", "Classical", "Hip-Hop / Trap", "EDM / House", "Reggae", "Ambient", "Acoustic", "Cinematic"].map((style) => <option key={style} value={style.toLowerCase()}>{style}</option>)}
                    </select>
                  </label>
                  {selectedMusicModel?.supportsLyrics && (
                    <label className="audio-studio-label">Lyrics (optional)
                      <textarea rows={4} maxLength={5000} value={musicLyrics} onChange={(event) => setMusicLyrics(event.target.value)} placeholder="Add lyrics for vocals, if desired…" />
                    </label>
                  )}
                  {selectedMusicModel?.supportsImages && (
                    <label className="audio-studio-label">Inspiration images (optional)
                      <input type="file" accept="image/jpeg,image/png,image/webp" multiple onChange={(event) => {
                        const files = Array.from(event.target.files ?? []).slice(0, 10)
                        setReferenceImages(files)
                        event.target.value = ""
                      }} />
                      <small>{referenceImages.length} / 10 selected</small>
                    </label>
                  )}
                  {selectedMusicModel && selectedMusicModel.outputFormats.length > 1 && (
                    <label className="audio-studio-label">Output format
                      <select value={outputFormat} onChange={(event) => setOutputFormat(event.target.value as "mp3" | "wav")}>
                        {selectedMusicModel.outputFormats.map((format) => <option key={format} value={format}>{format.toUpperCase()}</option>)}
                      </select>
                    </label>
                  )}
                </div>
              )}
            </>
          )}
          {mode !== "song" && (
            <div className="audio-voice-settings">
                  <label className="audio-studio-label">Voice · {voiceProvider}
                    {voiceProvider === "openai" || providerVoices.length > 0 ? (
                      <select value={voice} onChange={(event) => setVoice(event.target.value)}>
                        {(providerVoices.length > 0
                          ? providerVoices.map(({ id, name }) => [id, name])
                          : [["alloy", "Alloy · Neutral"], ["echo", "Echo · Warm"], ["fable", "Fable · Expressive"], ["onyx", "Onyx · Deep"], ["nova", "Nova · Bright"], ["shimmer", "Shimmer · Clear"]]
                        ).map(([id, label]) => <option key={id} value={id}>{label}</option>)}
                      </select>
                    ) : (
                      <input value={voice} onChange={(event) => setVoice(event.target.value)} placeholder="Enter provider voice name or ID" />
                    )}
                  </label>
              <label className="audio-studio-label">Speech model
                <select value={speechModel} onChange={(event) => setSpeechModel(event.target.value as "tts-1" | "tts-1-hd")}>
                  <option value="tts-1">TTS · Fast</option><option value="tts-1-hd">TTS HD · Higher quality</option>
                </select>
              </label>
              <label className="audio-studio-label">Language
                <select value={language} onChange={(event) => setLanguage(event.target.value)}>
                  <option value="en-US">English (US)</option><option value="en-GB">English (UK)</option><option value="so-SO">Somali</option><option value="ar">Arabic</option><option value="fr-FR">French</option><option value="sw">Swahili</option>
                </select>
              </label>
            </div>
          )}
          {error && <p className="audio-studio-error" role="alert">{error}</p>}
        </section>

        <button type="button" className="sx-generate audio-studio-generate" onClick={() => isAuthenticated ? void createAudio() : onRequireLogin()} disabled={generating}>
          {generating ? <><span className="audio-studio-spinner" /> Generating…</> : <>♫ Generate {mode === "song" ? "song" : mode === "voiceover" ? "voice-over" : "speech"}</>}
        </button>
        {onUpgrade && <button type="button" className="audio-studio-upgrade" onClick={onUpgrade}>Upgrade plan</button>}
        <p className="sx-credit-note">{isAuthenticated ? "Uses your Garad credits" : "Log in to generate audio"}</p>
      </aside>

      <main className="sx-canvas audio-studio-canvas">
        <header className="sx-canvas-bar">
          <nav className="sx-canvas-tabs" aria-label="Audio studio views">
            <button className={view === "creations" ? "active" : ""} onClick={() => setView("creations")} type="button">♫ Creations{history.length > 0 && <b>{history.length}</b>}</button>
            <button className={view === "templates" ? "active" : ""} onClick={() => setView("templates")} type="button">▤ Templates</button>
          </nav>
          <div className="sx-canvas-actions">
            {videoEditorUrl && <a className="audio-editor-link" href={videoEditorUrl}>Use music in Video Editor <span aria-hidden="true">↗</span></a>}
            <button className="sx-pill" onClick={() => void loadHistory()} disabled={historyLoading} type="button">{historyLoading ? "Refreshing…" : "↻ Refresh"}</button>
          </div>
        </header>

        <div className="sx-canvas-body audio-studio-body">
          {view === "templates" ? (
            <section className="audio-studio-main">
              <div className="audio-studio-section-heading"><div><h2>{modeLabels[mode]} templates</h2><p>Choose a starting point. It will be filled into the editor for you to customize.</p></div></div>
              <div className="audio-template-grid">
                {modeTemplates.map((template) => (
                  <button key={template.id} type="button" className={`audio-template-card ${selectedTemplate === template.id ? "selected" : ""}`} aria-pressed={selectedTemplate === template.id} onClick={() => { applyTemplate(template); setView("creations") }}>
                    <span className="audio-template-icon" aria-hidden="true">{mode === "song" ? "♫" : mode === "voiceover" ? "◖" : "▤"}</span>
                    <small>{template.category}</small><b>{template.name}</b><span className="audio-template-description">{template.description}</span>
                  </button>
                ))}
              </div>
            </section>
          ) : (
            <section className="audio-studio-main">
              <div className="audio-studio-section-heading"><div><h2>{activeAudio ? activeAudio.title : "Start creating"}</h2><p>{activeAudio ? `${modeLabels[activeAudio.kind === "song" ? "song" : activeAudio.kind === "voiceover" ? "voiceover" : "speech"]} · ${formatDate(activeAudio.createdAt)}` : "Create songs, voice-overs, and speech with your Garad audio tools."}</p></div></div>
              {activeAudio ? (
                <article className="audio-active-result">
                  <span className="audio-active-art" aria-hidden="true">{activeAudio.kind === "song" ? "♫" : "◖"}</span><b>{activeAudio.title}</b>
                  <audio controls preload="metadata" src={activeAudio.audioUrl}>Audio playback is not supported by your browser.</audio>
                  <a href={activeAudio.audioUrl} download>Download audio</a>
                  {activeAudio.kind === "song" && videoEditorUrl && <a href={videoEditorUrl}>Open Video Editor to add this track</a>}
                </article>
              ) : (
                <div className="audio-preview-empty"><span aria-hidden="true">♫</span><b>Your audio creations will appear here</b><p>Choose a template, add your text, and generate audio from the controls on the left.</p><button type="button" className="audio-studio-secondary" onClick={() => setView("templates")}>Browse {modeLabels[mode].toLowerCase()} templates</button></div>
              )}
              <div className="audio-history-heading"><h3>Recent audio</h3><span>{history.length}</span></div>
              {historyLoading ? <p className="audio-history-note">Loading your audio…</p> : history.length === 0 ? <p className="audio-history-note">Your generated audio will appear here.</p> : (
                <div className="audio-history-list">
                  {history.map((item) => (
                    <article key={item.id} className={`audio-history-item ${activeAudio?.id === item.id ? "active" : ""}`}>
                      <button type="button" className="audio-history-play" aria-label={`Play ${item.title}`} onClick={() => setActiveAudio(item)}>▶</button>
                      <button type="button" className="audio-history-info" onClick={() => setActiveAudio(item)}><b>{item.title}</b><small>{modeLabels[item.kind === "song" ? "song" : item.kind === "voiceover" ? "voiceover" : "speech"]} · {formatDate(item.createdAt)}</small></button>
                      {item.kind === "song" && item.recordId && <button type="button" className="audio-history-delete" aria-label={`Delete ${item.title}`} onClick={() => void deleteSong(item)}>×</button>}
                    </article>
                  ))}
                </div>
              )}
            </section>
          )}
        </div>
      </main>
    </section>
  )
}
