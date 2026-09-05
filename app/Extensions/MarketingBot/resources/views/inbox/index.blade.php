@extends('panel.layout.app', [
    'disable_tblr' => true,
    'disable_header' => true,
    'disable_footer' => true,
    'disable_titlebar' => true,
    'layout_wide' => true,
    'disable_mobile_bottom_menu' => true,
])
@section('title', __('MarketingBots'))
@section('titlebar_actions', '')

@push('css')
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('/assets/libs/picmo/picmo.min.css') }}"
    />
    <style>
        .lqd-chatbot-emoji .picmo__picker.picmo__picker {
            --background-color: hsl(var(--background));
            --secondary-background-color: hsl(var(--background));
            --border-color: hsl(0 0% 0% / 5%);
            --search-background-color: hsl(0 0% 0% / 5%);
            --search-height: 40px;
            --accent-color: hsl(var(--primary));
            --ui-font-size: 14px;
            /* --picker-width: 100%; */
            /* --emoji-size-multiplier: 1; */
            /* --emoji-preview-size: 2em; */
            --emoji-size: 1.75rem;
            /* --emoji-area-height: min(300px, calc(min(var(--lqd-ext-chat-window-h), calc(100vh - (var(--lqd-ext-chat-offset-y) * 2) - var(--lqd-ext-chat-trigger-h) - var(--lqd-ext-chat-window-y-offset))) - 140px)); */
        }

        .lqd-chatbot-emoji .picmo__picker .picmo__searchContainer .picmo__searchField {
            border-radius: 8px;
            font-size: 14px;
        }

        .lqd-chatbot-emoji .picmo__picker .picmo__emojiButton {
            border-radius: 6px;
        }

        .lqd-chatbot-emoji .picmo__picker .picmo__preview {
            display: none;
        }

        @media(min-width: 992px) {
            .lqd-header {
                display: none !important;
            }

            .focus-mode .lqd-page-content-container {
                max-width: 100% !important;
            }
        }
    </style>
@endpush

@push('after-body-open')
    <script>
        (() => {
            document.body.classList.add('navbar-shrinked');
        })();
    </script>
@endpush

@section('content')
    <div>
        <div
            class="lqd-ext-chatbot-history flex h-screen flex-col max-lg:[--header-height:65px] lg:flex-row"
            x-data="externalChatbotHistory"
        >
            <div
                class="lqd-ext-chatbot-history-sidebar group/history-sidebar relative flex shrink-0 flex-col bg-foreground/[3%] lg:w-[clamp(250px,27%,400px)]"
                :class="{ 'mobile-dropdown-open': mobileDropdownOpen }"
            >
                @includeIf('marketing-bot::inbox.particles.conversations-filter')

                <div
                    class="transition-all max-lg:fixed max-lg:bottom-0 max-lg:top-[calc(var(--header-height)+3.5rem)] max-lg:z-10 max-lg:flex max-lg:h-0 max-lg:w-full max-lg:flex-col max-lg:overflow-hidden max-lg:bg-background/90 max-lg:backdrop-blur-lg lg:contents max-lg:[&.active]:h-full"
                    :class="{ 'active': mobile.filtersVisible }"
                >
                    @includeIf('marketing-bot::inbox.particles.conversations-channel-filter')

                    @include('marketing-bot::inbox.particles.conversations-list')
                </div>
            </div>

            <div
                class="lqd-ext-chatbot-history-content-wrap flex h-full grow flex-col overflow-y-auto lg:w-1/2"
                x-ref="historyContentWrap"
            >
                @include('marketing-bot::inbox.particles.messages-header')

                <div
                    class="lqd-ext-chatbot-history-messages relative flex h-full flex-col gap-2 pt-10"
                    x-ref="historyMessages"
                >
                    <div class="mt-auto space-y-2 px-4 max-lg:pb-[calc(var(--bottom-menu-height)+2rem)] xl:px-10">
                        @include('marketing-bot::inbox.particles.messages-list')
                    </div>

                    @include('marketing-bot::inbox.particles.messages-form')
                </div>
            </div>

            <div
                class="lqd-ext-chatbot-contact-info flex flex-col border-s transition-all max-lg:fixed max-lg:bottom-0 max-lg:top-[calc(var(--header-height)+3.5rem)] max-lg:z-10 max-lg:h-0 max-lg:max-h-[calc(100%-var(--header-height)-3.5rem)] max-lg:w-full max-lg:overflow-hidden max-lg:bg-background/90 max-lg:backdrop-blur-lg lg:w-[clamp(250px,27%,400px)] max-lg:[&.active]:h-full"
                :class="{ 'active': mobile.contactInfoVisible }"
            >
                @include('marketing-bot::inbox.particles.contact-info-head')

                <div class="grid grow grid-cols-1 place-items-start overflow-y-auto">
                    @include('marketing-bot::inbox.particles.contact-info-tab-details')
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/libs/fslightbox/fslightbox.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it.min.js') }}"></script>
    @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('chatbot-agent'))
        <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    @endif

    <script
        src="https://cdn.ably.com/lib/ably.min-1.js"
        type="text/javascript"
    ></script>
    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('externalChatbotHistory', () => ({
                    chatbot_channel: 'all',
                    open: true,
                    chatsList: [],
                    activeChat: null,
                    fetching: false,
                    histories: [],
                    currentPage: 1,
                    allLoaded: false,
                    /**
                     * @type {IntersectionObserver}
                     */
                    loadMoreIO: null,
                    originalLoadMoreHref: null,
                    mobileDropdownOpen: false,
                    firstLoading: true,
                    filterConversation: true,
                    messageTime: null,
                    messagesSearchFormVisible: false,
                    conversationsSearchFormVisible: false,
                    contactInfo: {
                        activeTab: 'details',
                        editMode: false,
                    },
                    mobile: {
                        filtersVisible: false,
                        contactInfoVisible: false,
                    },

                    async init() {
                        this.onSendMessage = this.onSendMessage.bind(this);
                        this.setActiveChat = this.setActiveChat.bind(this);

                        this.originalLoadMoreHref = this.$refs.loadMore.href;

                        await this.fetchChats({
                            loadMore: true
                        });

                        this.scrollMessagesToBottom();
                    },
                    async loadMore() {
                        if (this.fetching || this.allLoaded) return;

                        this.currentPage += 1;
                        this.$refs.loadMore.href = this.$refs.loadMore.href.replace(/page=\d+/, `page=${this.currentPage}`);

                        await this.fetchChats({
                            loadMore: true
                        });
                    },
                    setupLoadMoreIO() {
                        const load = async (entry) => {};

                        this.loadMoreIO = new IntersectionObserver(async ([entry], observer) => {
                            if (entry.isIntersecting && !this.fetching && !this.allLoaded) {
                                await this.loadMore();
                            }
                        });

                        this.loadMoreIO.observe(this.$refs.loadMoreWrap);
                    },
                    async nameUpdate(element) {
                        @if ($app_is_demo)
                            toastr.error('This feature is disabled in Demo version.');
                            return;
                        @endif
                        let conversation_name = element.value.trim();

                        if (conversation_name == '') {
                            alert("Conversation Name cant' be empty!");
                            return;
                        }

                        const activeChatItem = this.chatsList.find(element => element.id == this.activeChat.id);

                        if (!activeChatItem) return;

                        activeChatItem.conversation_name = conversation_name;

                        const res = await fetch(
                            '{{ route('dashboard.user.marketing-bot.inbox.conversations.name.update') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    conversation_id: this.activeChat.id,
                                    conversation_name: conversation_name,
                                }),
                            });
                    },

                    async onSendMessage() {
                        @if ($app_is_demo)
                            toastr.error('This feature is disabled in Demo version.');
                            return;
                        @endif
                        const messageString = this.$refs.message.value.trim();

                        if (!messageString) return;

                        this.$refs.message.value = '';
                        this.$refs.submitBtn.setAttribute('disabled', 'disabled');

                        const newUserMessage = {
                            id: new Date().getTime(),
                            message: messageString,
                            role: 'assistant',
                            user: true,
                            created_at: new Date().toLocaleString()
                        };

                        let chatIndex = this.chatsList.findIndex(chat => chat.id == this.activeChat.id);

                        if (chatIndex !== -1) {
                            const histories = await Promise.resolve(this.chatsList[chatIndex].histories); // resolve et
                            if (!Array.isArray(histories)) {
                                this.chatsList[chatIndex].histories = [];
                            } else {
                                this.chatsList[chatIndex].histories = histories;
                            }

                            this.chatsList[chatIndex].histories.push(newUserMessage);
                        }

                        this.scrollMessagesToBottom();

                        const res = await fetch(
                            '{{ route('dashboard.user.marketing-bot.inbox.history') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    conversation_id: this.activeChat.id,
                                    message: messageString,
                                }),
                            });
                    },
                    scrollMessagesToBottom(smooth = false) {
                        this.$nextTick(() => {
                            this.$refs.historyMessages.scrollTo({
                                top: this.$refs.historyMessages.scrollHeight,
                                behavior: smooth ? 'smooth' : 'auto'
                            });
                        })
                    },
                    onMessageFieldHitEnter(event) {
                        if (!event.shiftKey) {
                            this.onSendMessage();
                        } else {
                            event.target.value += '\n';
                            event.target.scrollTop = event.target.scrollHeight
                        };
                    },
                    onMessageFieldInput(event) {
                        const messageString = this.$refs.message.value.trim();

                        if (messageString) {
                            this.$refs.submitBtn.removeAttribute('disabled');
                        } else {
                            this.$refs.submitBtn.setAttribute('disabled', 'disabled');
                        }
                    },
                    async setOpen(open) {
                        if (this.open === open) return;

                        const topNoticeBar = document.querySelector('.top-notice-bar');
                        const navbar = document.querySelector('.lqd-navbar');
                        const pageContentWrap = document.querySelector('.lqd-page-content-wrap');
                        const navbarExpander = document.querySelector('.lqd-navbar-expander');

                        this.open = open;

                        document.documentElement.style.overflow = this.open ? 'hidden' : '';

                        if (navbar) {
                            navbar.style.position = this.open ? 'fixed' : '';
                        }

                        if (pageContentWrap && navbar?.offsetWidth > 0) {
                            pageContentWrap.style.paddingInlineStart = this.open ? 'var(--navbar-width)' : '';
                        }

                        if (topNoticeBar) {
                            topNoticeBar.style.visibility = this.open ? 'hidden' : '';
                        }

                        if (navbarExpander) {
                            navbarExpander.style.visibility = this.open ? 'hidden' : '';
                            navbarExpander.style.opacity = this.open ? 0 : 1;
                        }

                        if (this.open) {
                            await this.fetchChats();
                            this.setupLoadMoreIO();
                        }
                    },
                    async fetchHistories(id) {
                        const res = await fetch(
                            '{{ route('dashboard.user.marketing-bot.inbox.history') }}?conversation_id=' + id, {
                                method: 'GET',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                            });

                        const data = await res.json();

                        return data.data;
                    },
                    async fetchChats(opts = {}) {
                        const options = {
                            filter: null,
                            loadMore: false,
                            ...opts
                        };

                        if (this.allLoaded && options.filter == null) {
                            return this.fetching = false;
                        }

                        if (options.filter != null) {
                            this.chatsList = [];
                            this.firstLoading = true;
                            this.filterConversation = options.filter;
                            this.activeChat = null;
                        }

                        this.fetching = true;

                        const res = await fetch(this.$refs.loadMore.href +
                            `&agentFilter=${this.filterConversation}&chatbot_channel=${this.chatbot_channel}`, {
                                method: 'GET',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                            });
                        const data = await res.json();
                        const {
                            data: conversations
                        } = data;

                        if (!res.ok || !conversations) {
                            if (data.message) {
                                toastr.error(data.message);
                            }
                            return;
                        }

                        this.chatsList.push(...conversations);

                        this.currentPage = data.meta.current_page;
                        this.allLoaded = this.currentPage >= data.meta.last_page;

                        if (!this.activeChat && this.chatsList.length) {
                            this.activeChat = this.chatsList[0];
                        }

                        this.fetching = false;
                        this.firstLoading = false;

                        this.scrollMessagesToBottom();
                    },
                    async handleConversationsSearch() {
                        const query = this.$refs.convSearchForm?.querySelector('input[name="conv_search"]')?.value?.trim();
                        this.fetching = true;

                        const res = await fetch(
                            '{{ route('dashboard.user.marketing-bot.inbox.conversations.search') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                                },
                                body: JSON.stringify({
                                    search: query,
                                }),
                            });
                        const data = await res.json();
                        const {
                            data: conversations
                        } = data;

                        if (!res.ok || !conversations) {
                            if (data.message) {
                                toastr.error(data.message);
                            }
                            return;
                        }

                        this.chatsList = conversations;
                        this.allLoaded = true;

                        if (conversations.length) {
                            this.activeChat = conversations[0];
                        } else {
                            this.activeChat = null;
                        }

                        this.fetching = false;
                        this.scrollMessagesToBottom();
                    },

                    async handleMessagesSearch(inputElement) {
                        const searchString = inputElement.value;

                        if (!searchString.trim()) {
                            if (this.activeChat) {
                                this.activeChat.histories = await this.fetchHistories(this.activeChat.id);
                            }
                            return;
                        }

                        if (this.activeChat?.histories) {
                            const filteredHistories = this.activeChat.histories.filter(message =>
                                message.message && message.message.toLowerCase().includes(searchString.toLowerCase())
                            );

                            this.activeChat.histories = filteredHistories;
                        }
                    },

                    async handleChangeTitle() {
                        // TODO: Implement change title logic
                    },
                    async handleDelete() {
                        if (!this.activeChat) {
                            alert('Please select the conversation which you want delete');
                            return;
                        }

                        // TODO: Implement delete logic
                        if (!confirm('Do you want delete this conversation history?')) {
                            return;
                        }

                        const res = await fetch(
                            '{{ route('dashboard.user.marketing-bot.inbox.destroy') }}', {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    conversation_id: this.activeChat.id,
                                }),
                            });
                        const responseData = await res.json();

                        if (responseData.status == 'success') {
                            this.chatsList = this.chatsList.filter((element) => {
                                return element.id != this.activeChat.id;
                            })

                            this.activeChat = this.chatsList[0];
                        }
                    },
                    async handleSummarize() {
                        // TODO: Implement delete logic
                    },
                    async setActiveChat(chatId) {
                        if (chatId == null) return;

                        let histories = await this.fetchHistories(chatId);

                        if (this.chatsList.length) {
                            this.chatsList = this.chatsList.map(chat => {
                                if (chat.id == chatId) {

                                    chat.histories = histories.map(history => {
                                        if (history.role == 'user' && history
                                            .read_at == null) {
                                            history.read_at = new Date()
                                                .toISOString();
                                        }
                                        return history;
                                    });

                                    chat.lastMessage.read_at = new Date().toISOString();
                                }
                                return chat;
                            });
                        }

                        this.activeChat = this.chatsList.find(chat => chat.id == chatId);
                        this.mobileDropdownOpen = false;
                        this.mobile.filtersVisible = false;
                    },
                    getFormattedString(string) {
                        if (!('markdownit' in window) || !string) return string;

                        string
                            .replace(/>(\s*\r?\n\s*)</g, '><')
                            .replace(/\n(?!.*\n)/, '');

                        const renderer = window.markdownit({
                            breaks: true,
                            highlight: (str, lang) => {
                                const language = lang && lang !== '' ? lang : 'md';
                                // const codeString = str.replace(/&/g, '&amp;').replace(/</g, '&lt;');
                                const codeString = str;

                                if (Prism.languages[language]) {
                                    const highlighted = Prism.highlight(codeString,
                                        Prism.languages[language], language);
                                    return `<pre class="language-${language}"><code data-lang="${language}" class="language-${language}">${highlighted}</code></pre>`;
                                }

                                return codeString;
                            }
                        });

                        renderer.use(function(md) {
                            md.core.ruler.after('inline', 'convert_links', function(state) {
                                state.tokens.forEach(function(blockToken) {
                                    if (blockToken.type !== 'inline')
                                        return;
                                    blockToken.children.forEach(function(
                                        token, idx) {
                                        const {
                                            content
                                        } = token;
                                        if (content.includes(
                                                '<a ')) {
                                            const linkRegex =
                                                /(.*)(<a\s+[^>]*\s+href="([^"]+)"[^>]*>([^<]*)<\/a>?)(.*)/;
                                            const linkMatch =
                                                content.match(
                                                    linkRegex);

                                            if (linkMatch) {
                                                const [, before, ,
                                                    href, text,
                                                    after
                                                ] = linkMatch;

                                                const beforeToken =
                                                    new state.Token(
                                                        'text', '',
                                                        0);
                                                beforeToken
                                                    .content =
                                                    before;

                                                const newToken =
                                                    new state.Token(
                                                        'link_open',
                                                        'a', 1);
                                                newToken.attrs = [
                                                    ['href',
                                                        href
                                                    ],
                                                    ['target',
                                                        '_blank'
                                                    ]
                                                ];
                                                const textToken =
                                                    new state.Token(
                                                        'text', '',
                                                        0);
                                                textToken.content =
                                                    text;
                                                const closingToken =
                                                    new state.Token(
                                                        'link_close',
                                                        'a', -1);

                                                const afterToken =
                                                    new state.Token(
                                                        'text', '',
                                                        0);
                                                afterToken.content =
                                                    after;

                                                blockToken.children
                                                    .splice(idx, 1,
                                                        beforeToken,
                                                        newToken,
                                                        textToken,
                                                        closingToken,
                                                        afterToken);
                                            }
                                        }
                                    });
                                });
                            });
                        });

                        return renderer.render(renderer.utils.unescapeAll(string));
                    },
                    getUnreadMessages(chatItem) {
                        let chat = chatItem;
                        try {
                            if (typeof(chatItem) == 'number' || typeof(chatItem) == 'string') {
                                chat = this.chatsList?.find((element) => {
                                    return element.id == chatItem
                                });

                                if (chatItem == this.activeChat.id) {
                                    chat?.histories?.forEach(element => {
                                        element.read_at = new Date();
                                    });
                                    return 0;
                                }
                            } else if (chat.id == this.activeChat.id) {
                                chat?.histories?.forEach(element => {
                                    element.read_at = new Date();
                                });
                                return 0;
                            }

                            return chat?.histories?.filter((history) => {
                                return history.role == 'user' && history.read_at == null
                            })?.length;
                        } catch (e) {
                            return 0
                        }
                    },
                    getAllUnreadMessages() {
                        const unreadMessages = this.chatsList?.reduce((previousValue, element) => {
                            return previousValue + this.getUnreadMessages(element) ?? 0;
                        }, 0);

                        return unreadMessages;
                    },
                    getDiffHumanTime(time) {
                        const diff = Math.floor((new Date() - new Date(time)) / 1000);

                        return diff < 60 ? " {{ __('Just now') }}" :
                            diff < 3600 ? (Math.floor(diff / 60) === 1 ?
                                "1 {{ __('minute ago') }}" : Math.floor(diff / 60) +
                                " {{ __('minutes ago') }}") :
                            diff < 86400 ? (Math.floor(diff / 3600) === 1 ?
                                "1 {{ __('hour ago') }}" : Math.floor(diff / 3600) +
                                " {{ __('hours ago') }}") :
                            Math.floor(diff / 86400) === 1 ? "1 {{ __('day ago') }}" : Math.floor(
                                diff / 86400) + " {{ __('days ago') }}"
                    },
                    getShortDiffHumanTime(time) {
                        const diff = Math.floor((new Date() - new Date(time)) / 1000);

                        return diff < 60 ? '{{ __('Just now') }}' :
                            diff < 3600 ? Math.floor(diff / 60) + '{{ __('m') }}' :
                            diff < 86400 ? Math.floor(diff / 3600) + '{{ __('h') }}' :
                            Math.floor(diff / 86400) + '{{ __('d') }}'
                    }
                }));
            });
        })();
    </script>
@endpush
