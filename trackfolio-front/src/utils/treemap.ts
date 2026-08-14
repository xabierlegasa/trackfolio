/**
 * Squarified treemap layout (values → absolute rects in container units).
 * Returns x/y/width/height as percentages of the container (0–100).
 */

export interface TreemapLeafInput {
  id: string
  value: number
}

export interface TreemapLeafLayout extends TreemapLeafInput {
  x: number
  y: number
  width: number
  height: number
}

export function layoutTreemap(
  items: TreemapLeafInput[],
  containerWidth = 100,
  containerHeight = 100,
): TreemapLeafLayout[] {
  const positive = items
    .filter((item) => item.value > 0)
    .map((item) => ({ ...item }))
    .sort((a, b) => b.value - a.value)

  if (positive.length === 0) {
    return []
  }

  const total = positive.reduce((sum, item) => sum + item.value, 0)
  const result: TreemapLeafLayout[] = []
  squarify(
    positive,
    total,
    { x: 0, y: 0, width: containerWidth, height: containerHeight },
    result,
  )
  return result
}

type Rect = { x: number; y: number; width: number; height: number }

function squarify(
  children: TreemapLeafInput[],
  remainingValue: number,
  rect: Rect,
  out: TreemapLeafLayout[],
): void {
  if (children.length === 0 || remainingValue <= 0 || rect.width <= 0 || rect.height <= 0) {
    return
  }

  if (children.length === 1) {
    const leaf = children[0]
    out.push({
      id: leaf.id,
      value: leaf.value,
      x: rect.x,
      y: rect.y,
      width: rect.width,
      height: rect.height,
    })
    return
  }

  const horizontal = rect.width >= rect.height
  const side = horizontal ? rect.height : rect.width
  let row: TreemapLeafInput[] = []
  let rowValue = 0
  let bestWorst = Infinity

  for (let i = 0; i < children.length; i++) {
    const candidate = children[i]
    const nextRow = [...row, candidate]
    const nextRowValue = rowValue + candidate.value
    const worst = worstAspectRatio(nextRow, nextRowValue, remainingValue, side, rect)

    if (row.length > 0 && worst > bestWorst) {
      layoutRow(row, rowValue, remainingValue, rect, horizontal, out)
      const consumed = rowValue / remainingValue
      const nextRect = shrinkRect(rect, consumed, horizontal)
      squarify(children.slice(i), remainingValue - rowValue, nextRect, out)
      return
    }

    row = nextRow
    rowValue = nextRowValue
    bestWorst = worst
  }

  layoutRow(row, rowValue, remainingValue, rect, horizontal, out)
}

function worstAspectRatio(
  row: TreemapLeafInput[],
  rowValue: number,
  totalValue: number,
  side: number,
  rect: Rect,
): number {
  if (rowValue <= 0 || totalValue <= 0 || side <= 0) {
    return Infinity
  }

  const area = (rect.width * rect.height * rowValue) / totalValue
  const rowSide = area / side
  let worst = 0
  for (const item of row) {
    const itemSide = (rowSide * item.value) / rowValue
    if (itemSide <= 0) {
      return Infinity
    }
    const ratio = Math.max(side / itemSide, itemSide / side)
    if (ratio > worst) {
      worst = ratio
    }
  }
  return worst
}

function layoutRow(
  row: TreemapLeafInput[],
  rowValue: number,
  totalValue: number,
  rect: Rect,
  horizontal: boolean,
  out: TreemapLeafLayout[],
): void {
  if (rowValue <= 0 || totalValue <= 0) {
    return
  }

  const fraction = rowValue / totalValue
  if (horizontal) {
    const rowWidth = rect.width * fraction
    let y = rect.y
    for (const item of row) {
      const h = rect.height * (item.value / rowValue)
      out.push({
        id: item.id,
        value: item.value,
        x: rect.x,
        y,
        width: rowWidth,
        height: h,
      })
      y += h
    }
  } else {
    const rowHeight = rect.height * fraction
    let x = rect.x
    for (const item of row) {
      const w = rect.width * (item.value / rowValue)
      out.push({
        id: item.id,
        value: item.value,
        x,
        y: rect.y,
        width: w,
        height: rowHeight,
      })
      x += w
    }
  }
}

function shrinkRect(rect: Rect, consumedFraction: number, horizontal: boolean): Rect {
  if (horizontal) {
    const used = rect.width * consumedFraction
    return {
      x: rect.x + used,
      y: rect.y,
      width: Math.max(rect.width - used, 0),
      height: rect.height,
    }
  }

  const used = rect.height * consumedFraction
  return {
    x: rect.x,
    y: rect.y + used,
    width: rect.width,
    height: Math.max(rect.height - used, 0),
  }
}

