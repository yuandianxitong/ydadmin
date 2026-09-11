//菜单主题类型
export enum ThemeEnum {
    LIGHT = 'light',
    DARK = 'dark'
}

// 菜单类型
export enum MenuEnum {
    CATALOGUE = 'M',
    MENU = 'C',
    BUTTON = 'A'
}

// 屏幕
export enum ScreenEnum {
    SM = 640,
    MD = 768,
    LG = 1024,
    XL = 1280,
    '2XL' = 1536
}

// 客户端类型
export enum ClientEnum {
    MP_WEIXIN = 1, // 微信-小程序
    OA_WEIXIN = 2, // 微信-公众号
    H5 = 3, // H5
    PC = 4, // PC
    IOS = 5, //苹果
    ANDROID = 6 //安卓
}

// 客户端类型名称 - 使用函数以支持 i18n
import { t } from '@/utils/i18n'

export function getClientMap(): Record<number, string> {
    return {
        [ClientEnum.MP_WEIXIN]: t('client.mpWeixin'),
        [ClientEnum.OA_WEIXIN]: t('client.oaWeixin'),
        [ClientEnum.H5]: t('client.h5'),
        [ClientEnum.PC]: t('client.pc'),
        [ClientEnum.IOS]: t('client.ios'),
        [ClientEnum.ANDROID]: t('client.android')
    }
}
