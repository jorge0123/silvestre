<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BadgeCheck } from '@lucide/vue';
import BusinessAvatar from '@/components/feed/BusinessAvatar.vue';
import FollowButton from '@/components/feed/FollowButton.vue';
import RatingStars from '@/components/feed/RatingStars.vue';
import { show as businessShow } from '@/routes/business';
import type { BusinessCard } from '@/types/feed';

defineProps<{ business: BusinessCard }>();
</script>

<template>
    <div class="bg-card flex w-56 flex-none flex-col overflow-hidden rounded-2xl border">
        <Link :href="businessShow(business.slug)" class="bg-muted relative block h-20">
            <img v-if="business.cover" :src="business.cover" alt="" class="size-full object-cover" loading="lazy" />
        </Link>
        <div class="-mt-7 flex flex-1 flex-col px-3.5 pb-3.5">
            <Link :href="businessShow(business.slug)" class="self-start">
                <span class="bg-card inline-block rounded-full p-0.5">
                    <BusinessAvatar :src="business.avatar" :name="business.name" size="lg" />
                </span>
            </Link>
            <Link :href="businessShow(business.slug)" class="mt-1 flex items-center gap-1 font-bold hover:underline">
                <span class="truncate">{{ business.name }}</span>
                <BadgeCheck v-if="business.verified" class="text-brand size-4 flex-none" />
            </Link>
            <p class="text-muted-foreground truncate text-xs">{{ business.category }} · {{ business.zone }}</p>
            <p class="mt-1.5 flex items-center gap-1.5 text-xs">
                <template v-if="business.rating !== null">
                    <RatingStars :value="business.rating" size="size-3" />
                    <span class="font-bold tabular-nums">{{ business.rating.toFixed(1) }}</span>
                    <span class="text-muted-foreground">({{ business.ratingCount }})</span>
                </template>
                <span v-else class="bg-wait-soft text-wait rounded-full px-2 py-0.5 font-bold">Nuevo</span>
            </p>
            <div class="mt-3">
                <FollowButton :slug="business.slug" :following="false" size="sm" />
            </div>
        </div>
    </div>
</template>
