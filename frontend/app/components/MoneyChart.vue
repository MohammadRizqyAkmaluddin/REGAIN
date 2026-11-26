<script setup lang="ts">
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { BarChart, LineChart, PieChart } from 'echarts/charts'
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components'
import VChart from 'vue-echarts'
import type { ExpenseByCategoryItem, MonthlyTrendItem } from '~/types/regain'
import { formatCompactRupiah, formatRupiah } from '~/utils/format'

use([CanvasRenderer, BarChart, LineChart, PieChart, GridComponent, TooltipComponent, LegendComponent])

const props = defineProps<{
  mode: 'trend' | 'category'
  trend?: MonthlyTrendItem[]
  categories?: ExpenseByCategoryItem[]
}>()

const { theme } = useTheme()

const option = computed(() => {
  const isDark = theme.value === 'dark'
  const textColor = isDark ? '#a1a1aa' : '#52525b'
  const splitColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(15,23,42,0.08)'

  if (props.mode === 'category') {
    const items = props.categories ?? []
    return {
      tooltip: {
        trigger: 'item',
        backgroundColor: isDark ? '#18181b' : '#ffffff',
        borderColor: isDark ? '#27272a' : '#e4e4e7',
        textStyle: { color: isDark ? '#fafafa' : '#09090b', fontSize: 12 },
        formatter: (p: any) => `${p.name}<br/><strong>${formatRupiah(p.value)}</strong> (${p.percent}%)`,
      },
      series: [
        {
          type: 'pie',
          radius: ['54%', '80%'],
          avoidLabelOverlap: true,
          itemStyle: { borderRadius: 6, borderWidth: 2, borderColor: isDark ? '#111114' : '#ffffff' },
          label: { show: false },
          data: items.map(i => ({
            name: i.category_name,
            value: Number.parseFloat(i.total),
            itemStyle: { color: i.color },
          })),
        },
      ],
    }
  }

  const data = props.trend ?? []
  return {
    tooltip: {
      trigger: 'axis',
      backgroundColor: isDark ? '#18181b' : '#ffffff',
      borderColor: isDark ? '#27272a' : '#e4e4e7',
      textStyle: { color: isDark ? '#fafafa' : '#09090b', fontSize: 12 },
      valueFormatter: (val: any) => formatRupiah(val),
    },
    legend: {
      bottom: 0,
      textStyle: { color: textColor, fontSize: 11 },
      itemWidth: 10,
      itemHeight: 10,
    },
    grid: { top: 14, left: 6, right: 8, bottom: 34, containLabel: true },
    xAxis: {
      type: 'category',
      data: data.map(d => d.label),
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
        name: 'Pemasukan',
        type: 'bar',
        barMaxWidth: 16,
        itemStyle: { color: '#10b981', borderRadius: [6, 6, 0, 0] },
        data: data.map(d => Number.parseFloat(d.income)),
      },
      {
        name: 'Pengeluaran',
        type: 'bar',
        barMaxWidth: 16,
        itemStyle: { color: '#f43f5e', borderRadius: [6, 6, 0, 0] },
        data: data.map(d => Number.parseFloat(d.expenses)),
      },
      {
        name: 'Kekayaan Bersih',
        type: 'line',
        smooth: true,
        symbolSize: 6,
        lineStyle: { width: 2.5, color: '#38bdf8' },
        itemStyle: { color: '#38bdf8' },
        data: data.map(d => Number.parseFloat(d.net_worth)),
      },
    ],
  }
})
</script>

<template>
  <div class="w-full" :class="mode === 'category' ? 'h-56' : 'h-64'">
    <VChart :option="option" autoresize class="h-full w-full" />
  </div>
</template>
