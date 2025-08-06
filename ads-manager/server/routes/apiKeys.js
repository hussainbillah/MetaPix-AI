const express = require('express');
const Joi = require('joi');
const User = require('../models/User');
const { auth } = require('../middleware/auth');
const logger = require('../utils/logger');

const router = express.Router();

// Validation schemas
const createApiKeySchema = Joi.object({
  name: Joi.string().max(50).required(),
  permissions: Joi.array().items(Joi.string().valid(
    'campaigns:read',
    'campaigns:write',
    'ads:read',
    'ads:write',
    'analytics:read',
    'platforms:read',
    'users:read'
  )).optional()
});

const updateApiKeySchema = Joi.object({
  name: Joi.string().max(50).optional(),
  isActive: Joi.boolean().optional(),
  permissions: Joi.array().items(Joi.string().valid(
    'campaigns:read',
    'campaigns:write',
    'ads:read',
    'ads:write',
    'analytics:read',
    'platforms:read',
    'users:read'
  )).optional()
});

// @route   GET /api/api-keys
// @desc    Get all API keys for current user
// @access  Private
router.get('/', auth, async (req, res) => {
  try {
    const user = await User.findById(req.user.id);
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    // Return API keys without the actual key value for security
    const apiKeys = user.apiKeys.map(key => ({
      id: key._id,
      name: key.name,
      permissions: key.permissions,
      isActive: key.isActive,
      lastUsed: key.lastUsed,
      createdAt: key.createdAt,
      // Show only first 8 characters of key for identification
      keyPreview: key.key.substring(0, 8) + '...'
    }));

    res.json({
      apiKeys,
      total: apiKeys.length,
      active: apiKeys.filter(key => key.isActive).length
    });

  } catch (error) {
    logger.error('Get API keys error:', error);
    res.status(500).json({ error: 'Server error while fetching API keys' });
  }
});

// @route   POST /api/api-keys
// @desc    Create a new API key
// @access  Private
router.post('/', auth, async (req, res) => {
  try {
    // Validate input
    const { error, value } = createApiKeySchema.validate(req.body);
    if (error) {
      return res.status(400).json({ error: error.details[0].message });
    }

    const { name, permissions = [] } = value;

    const user = await User.findById(req.user.id);
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    // Check if API key name already exists
    const existingKey = user.apiKeys.find(key => key.name === name);
    if (existingKey) {
      return res.status(400).json({ error: 'API key with this name already exists' });
    }

    // Generate new API key
    const apiKey = user.generateApiKey(name, permissions);
    await user.save();

    logger.info(`New API key created for user: ${user.email}, name: ${name}`);

    res.status(201).json({
      message: 'API key created successfully',
      apiKey: {
        id: apiKey._id,
        name: apiKey.name,
        key: apiKey.key, // Only return the full key on creation
        permissions: apiKey.permissions,
        isActive: apiKey.isActive,
        createdAt: apiKey.createdAt
      }
    });

  } catch (error) {
    logger.error('Create API key error:', error);
    res.status(500).json({ error: 'Server error while creating API key' });
  }
});

// @route   GET /api/api-keys/:id
// @desc    Get specific API key details
// @access  Private
router.get('/:id', auth, async (req, res) => {
  try {
    const { id } = req.params;

    const user = await User.findById(req.user.id);
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    const apiKey = user.apiKeys.id(id);
    if (!apiKey) {
      return res.status(404).json({ error: 'API key not found' });
    }

    res.json({
      apiKey: {
        id: apiKey._id,
        name: apiKey.name,
        permissions: apiKey.permissions,
        isActive: apiKey.isActive,
        lastUsed: apiKey.lastUsed,
        createdAt: apiKey.createdAt,
        // Show only first 8 characters of key for identification
        keyPreview: apiKey.key.substring(0, 8) + '...'
      }
    });

  } catch (error) {
    logger.error('Get API key error:', error);
    res.status(500).json({ error: 'Server error while fetching API key' });
  }
});

// @route   PUT /api/api-keys/:id
// @desc    Update API key
// @access  Private
router.put('/:id', auth, async (req, res) => {
  try {
    const { id } = req.params;

    // Validate input
    const { error, value } = updateApiKeySchema.validate(req.body);
    if (error) {
      return res.status(400).json({ error: error.details[0].message });
    }

    const user = await User.findById(req.user.id);
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    const apiKey = user.apiKeys.id(id);
    if (!apiKey) {
      return res.status(404).json({ error: 'API key not found' });
    }

    // Update API key fields
    Object.keys(value).forEach(key => {
      apiKey[key] = value[key];
    });

    await user.save();

    logger.info(`API key updated for user: ${user.email}, key: ${apiKey.name}`);

    res.json({
      message: 'API key updated successfully',
      apiKey: {
        id: apiKey._id,
        name: apiKey.name,
        permissions: apiKey.permissions,
        isActive: apiKey.isActive,
        lastUsed: apiKey.lastUsed,
        createdAt: apiKey.createdAt,
        keyPreview: apiKey.key.substring(0, 8) + '...'
      }
    });

  } catch (error) {
    logger.error('Update API key error:', error);
    res.status(500).json({ error: 'Server error while updating API key' });
  }
});

// @route   DELETE /api/api-keys/:id
// @desc    Delete API key
// @access  Private
router.delete('/:id', auth, async (req, res) => {
  try {
    const { id } = req.params;

    const user = await User.findById(req.user.id);
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    const apiKey = user.apiKeys.id(id);
    if (!apiKey) {
      return res.status(404).json({ error: 'API key not found' });
    }

    // Remove API key from array
    user.apiKeys.pull(id);
    await user.save();

    logger.info(`API key deleted for user: ${user.email}, key: ${apiKey.name}`);

    res.json({
      message: 'API key deleted successfully'
    });

  } catch (error) {
    logger.error('Delete API key error:', error);
    res.status(500).json({ error: 'Server error while deleting API key' });
  }
});

// @route   POST /api/api-keys/:id/regenerate
// @desc    Regenerate API key
// @access  Private
router.post('/:id/regenerate', auth, async (req, res) => {
  try {
    const { id } = req.params;

    const user = await User.findById(req.user.id);
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    const apiKey = user.apiKeys.id(id);
    if (!apiKey) {
      return res.status(404).json({ error: 'API key not found' });
    }

    // Generate new key
    const crypto = require('crypto');
    const newKey = crypto.randomBytes(32).toString('hex');
    
    // Store old key for logging
    const oldKeyName = apiKey.name;
    
    // Update key
    apiKey.key = newKey;
    apiKey.lastUsed = new Date();
    
    await user.save();

    logger.info(`API key regenerated for user: ${user.email}, key: ${oldKeyName}`);

    res.json({
      message: 'API key regenerated successfully',
      apiKey: {
        id: apiKey._id,
        name: apiKey.name,
        key: apiKey.key, // Return new key
        permissions: apiKey.permissions,
        isActive: apiKey.isActive,
        createdAt: apiKey.createdAt
      }
    });

  } catch (error) {
    logger.error('Regenerate API key error:', error);
    res.status(500).json({ error: 'Server error while regenerating API key' });
  }
});

// @route   POST /api/api-keys/:id/toggle
// @desc    Toggle API key active status
// @access  Private
router.post('/:id/toggle', auth, async (req, res) => {
  try {
    const { id } = req.params;

    const user = await User.findById(req.user.id);
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    const apiKey = user.apiKeys.id(id);
    if (!apiKey) {
      return res.status(404).json({ error: 'API key not found' });
    }

    // Toggle active status
    apiKey.isActive = !apiKey.isActive;
    await user.save();

    logger.info(`API key ${apiKey.isActive ? 'activated' : 'deactivated'} for user: ${user.email}, key: ${apiKey.name}`);

    res.json({
      message: `API key ${apiKey.isActive ? 'activated' : 'deactivated'} successfully`,
      apiKey: {
        id: apiKey._id,
        name: apiKey.name,
        permissions: apiKey.permissions,
        isActive: apiKey.isActive,
        lastUsed: apiKey.lastUsed,
        createdAt: apiKey.createdAt,
        keyPreview: apiKey.key.substring(0, 8) + '...'
      }
    });

  } catch (error) {
    logger.error('Toggle API key error:', error);
    res.status(500).json({ error: 'Server error while toggling API key' });
  }
});

// @route   GET /api/api-keys/usage/stats
// @desc    Get API key usage statistics
// @access  Private
router.get('/usage/stats', auth, async (req, res) => {
  try {
    const user = await User.findById(req.user.id);
    if (!user) {
      return res.status(404).json({ error: 'User not found' });
    }

    const stats = {
      total: user.apiKeys.length,
      active: user.apiKeys.filter(key => key.isActive).length,
      inactive: user.apiKeys.filter(key => !key.isActive).length,
      recentlyUsed: user.apiKeys.filter(key => {
        if (!key.lastUsed) return false;
        const daysSinceLastUse = (new Date() - new Date(key.lastUsed)) / (1000 * 60 * 60 * 24);
        return daysSinceLastUse <= 7;
      }).length,
      neverUsed: user.apiKeys.filter(key => !key.lastUsed).length
    };

    res.json(stats);

  } catch (error) {
    logger.error('Get API key stats error:', error);
    res.status(500).json({ error: 'Server error while fetching API key statistics' });
  }
});

module.exports = router;