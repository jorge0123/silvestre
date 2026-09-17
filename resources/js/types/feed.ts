export type BusinessCard = {
    id: number;
    name: string;
    slug: string;
    intro: string | null;
    avatar: string | null;
    cover: string | null;
    category: string | null;
    zone: string | null;
    offering: 'productos' | 'servicios' | 'ambos';
    /** null mientras tenga menos de 5 reseñas: el promedio se oculta. */
    rating: number | null;
    ratingCount: number;
    followers: number;
    verified: boolean;
};

export type MediaItem = {
    type: 'image' | 'video';
    url: string;
    poster: string | null;
};

export type PostItem = {
    id: number;
    title: string;
    body: string;
    ago: string | null;
    date: string | null;
    media: MediaItem[];
    reactions: number;
    comments: number;
    reacted: boolean;
    business: BusinessCard;
};

export type StoryItem = {
    id: number;
    type: 'image' | 'video';
    url: string;
    poster: string | null;
    caption: string | null;
    ago: string | null;
};

export type StoryGroup = {
    business: { name: string; slug: string; avatar: string | null };
    stories: StoryItem[];
};

export type PostVariant = 'following' | 'discovery' | 'sponsored';

export type FeedItem =
    | { kind: 'post'; key: string; variant: PostVariant; post: PostItem }
    | { kind: 'suggested'; key: string; title: string; businesses: BusinessCard[] }
    | { kind: 'caught_up'; key: string };

export type FeedPage = {
    items: FeedItem[];
    page: number;
    hasMore: boolean;
};

export type CommentItem = {
    id: number;
    parentId: number | null;
    body: string;
    ago: string | null;
    edited: boolean;
    author: {
        name: string;
        avatar: string | null;
        slug: string | null;
        isBusiness: boolean;
        isPostOwner: boolean;
    };
    can: { edit: boolean; delete: boolean; report: boolean };
    replies: CommentItem[];
};
