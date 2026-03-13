import { beforeEach, describe, expect, it, vi } from 'vitest'

import cache from '../cache'

// Mock localStorage since happy-dom's implementation is incomplete
const store: Record<string, string> = {}
const localStorageMock = {
    getItem: vi.fn((key: string) => store[key] ?? null),
    setItem: vi.fn((key: string, value: string) => {
        store[key] = value
    }),
    removeItem: vi.fn((key: string) => {
        delete store[key]
    }),
    clear: vi.fn(() => {
        Object.keys(store).forEach((k) => delete store[k])
    })
}

Object.defineProperty(window, 'localStorage', { value: localStorageMock })

describe('cache', () => {
    beforeEach(() => {
        Object.keys(store).forEach((k) => delete store[k])
        vi.clearAllMocks()
    })

    it('should set and get a string value', () => {
        cache.set('test_key', 'hello')
        expect(cache.get('test_key')).toBe('hello')
    })

    it('should set and get an object value', () => {
        const obj = { name: 'test', count: 42 }
        cache.set('obj_key', obj)
        expect(cache.get('obj_key')).toEqual(obj)
    })

    it('should return null for non-existent key', () => {
        expect(cache.get('non_existent')).toBeNull()
    })

    it('should remove a key', () => {
        cache.set('remove_key', 'value')
        cache.remove('remove_key')
        expect(cache.get('remove_key')).toBeNull()
    })

    it('should clear all cache', () => {
        cache.set('key1', 'val1')
        cache.set('key2', 'val2')
        cache.clear()
        expect(cache.get('key1')).toBeNull()
        expect(cache.get('key2')).toBeNull()
    })

    it('should prefix keys with the configured prefix', () => {
        expect(cache.getKey('mykey')).toBe('like_admin_mykey')
    })

    it('should handle boolean values', () => {
        cache.set('bool_true', true)
        cache.set('bool_false', false)
        expect(cache.get('bool_true')).toBe(true)
        expect(cache.get('bool_false')).toBe(false)
    })

    it('should handle array values', () => {
        const arr = [1, 2, 3]
        cache.set('arr_key', arr)
        expect(cache.get('arr_key')).toEqual(arr)
    })

    it('should handle number values', () => {
        cache.set('num_key', 42)
        expect(cache.get('num_key')).toBe(42)
    })

    it('should call localStorage.setItem with prefixed key', () => {
        cache.set('my_key', 'my_value')
        expect(localStorageMock.setItem).toHaveBeenCalledWith(
            'like_admin_my_key',
            expect.any(String)
        )
    })
})
