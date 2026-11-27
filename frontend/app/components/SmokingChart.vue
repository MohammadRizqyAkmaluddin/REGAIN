<script setup lang="ts">
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import VChart from 'vue-echarts'
import { formatCompactRupiah, formatRupiah } from '~/utils/format'

use([CanvasRenderer, BarChart, GridComponent, TooltipComponent])

const props = defineProps<{
  items: Array<{ label: string; estimated_saved: string; smoke_free_days: number }>
}>()

const { theme } = useTheme()

const option = computed(() => {
  const isDark = theme.value === 'dark'
  const textColor = isDark ? '#a1a1aa' : '#52525b'
  const splitColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(15,23,42,0.08)'

  return {
    tooltip: {
      trigger: 'axis',
      backgroundColor: isDark ? '#18181b' : '#ffffff',
      borderColor: isDark ? '#27272a' : '#e4e4e7',
      textStyle: { color: isDark ? '#fafafa' : '#09090b', fontSize: 12 },
      formatter: (params: any) => {
        const p = Array.isArray(params) ? params[0] : params
        const item = props.items[p.dataIndex]
        return `${p.name}<br/>Estimasi Hemat: <strong>${formatRupiah(p.value)}</strong><br/>Hari Bebas Rokok: ${item?.smoke_free_days ?? 0}`
      },
    },
    grid: { top: 12, left: 6, right: 8, bottom: 8, containLabel: true },
    xAxis: {
      type: 'category',
      data: props.items.map(i => i.label),
      axisLine: { show: false },
      axisTick: { show: false },
      axisLabel: { color: textColor, fontSize: 11 },
    },
    yAxis: {
      type: 'value',
      splitLine: { lineStyle: { color: splitColor } },
      axisLabel: { color: textColor, fontSize: 10, formatter: (v: number) => formatCompactRupiah(v) },
    },
    series: [
      {
        type: 'bar',
        barMaxWidth: 26,
        itemStyle: { color: isDark ? '#e4e4e7' : '#313131', borderRadius: [8, 8, 0, 0] },
        data: props.items.map(i => Number.parseFloat(i.estimated_saved)),
      },
    ],
  }
})
</script>

<template>
  <div class="h-56 w-full">
    <VChart :option="option" autoresize class="h-full w-full" />
  </div>
</template>
