export function isSingleBlockTemplate(postType, layoutType) {
  return postType === 'dsf_layout' && layoutType === 'header'
}

export function normalizeTemplateBlocks(blocks, postType, layoutType) {
  const safeBlocks = Array.isArray(blocks) ? blocks : []
  return isSingleBlockTemplate(postType, layoutType) ? safeBlocks.slice(0, 1) : safeBlocks
}

export function canAddTemplateBlock(blocks, postType, layoutType) {
  return !isSingleBlockTemplate(postType, layoutType) || normalizeTemplateBlocks(blocks, postType, layoutType).length === 0
}
