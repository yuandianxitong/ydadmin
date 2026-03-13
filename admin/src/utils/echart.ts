// src/core/plugins/echart.ts

// 引入 ECharts 核心模块与必要组件
import {
    BarChart,
    LineChart,
    MapChart,
    PictorialBarChart,
    PieChart,
    RadarChart,
    ScatterChart
} from 'echarts/charts'
import {
    AriaComponent,
    CalendarComponent,
    DataZoomComponent,
    GraphicComponent,
    GridComponent,
    LegendComponent,
    ParallelComponent,
    PolarComponent,
    RadarComponent,
    TimelineComponent,
    TitleComponent,
    ToolboxComponent,
    TooltipComponent,
    VisualMapComponent
} from 'echarts/components'
import * as echarts from 'echarts/core'
import { LabelLayout, UniversalTransition } from 'echarts/features'
import { CanvasRenderer } from 'echarts/renderers'

// 注册所需的组件
echarts.use([
    LegendComponent,
    TitleComponent,
    TooltipComponent,
    GridComponent,
    PolarComponent,
    AriaComponent,
    ParallelComponent,
    BarChart,
    LineChart,
    PieChart,
    MapChart,
    RadarChart,
    PictorialBarChart,
    RadarComponent,
    ToolboxComponent,
    DataZoomComponent,
    VisualMapComponent,
    TimelineComponent,
    CalendarComponent,
    GraphicComponent,
    ScatterChart,
    CanvasRenderer,
    LabelLayout,
    UniversalTransition
])

export default echarts
