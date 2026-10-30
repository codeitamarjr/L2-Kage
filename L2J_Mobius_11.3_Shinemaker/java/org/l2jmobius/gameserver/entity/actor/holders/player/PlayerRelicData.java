/*
 * Copyright (c) 2013 L2jMobius
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
 * IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
package org.l2jmobius.gameserver.entity.actor.holders.player;

/**
 * @author CostyKiller
 */
public class PlayerRelicData
{
	private int _relicId;
	private int _relicLevel;
	private int _relicCount;
	private int _relicIndex;
	private long _relicSummonTime;
	private int _replaceAttemptsUsed;
	
	public PlayerRelicData(int relicId, int relicLevel, int relicCount, int relicIndex, long relicSummonTime)
	{
		this(relicId, relicLevel, relicCount, relicIndex, relicSummonTime, 0);
	}
	
	public PlayerRelicData(int relicId, int relicLevel, int relicCount, int relicIndex, long relicSummonTime, int replaceAttemptsUsed)
	{
		_relicId = relicId;
		_relicLevel = relicLevel;
		_relicCount = relicCount;
		_relicIndex = relicIndex;
		_relicSummonTime = relicSummonTime;
		_replaceAttemptsUsed = replaceAttemptsUsed;
	}
	
	public int getRelicId()
	{
		return _relicId;
	}
	
	public int getReplaceAttemptsUsed()
	{
		return _replaceAttemptsUsed;
	}
	
	public int getRelicLevel()
	{
		return _relicLevel;
	}
	
	public int getRelicCount()
	{
		return _relicCount;
	}
	
	public int getRelicIndex()
	{
		return _relicIndex;
	}
	
	public long getRelicSummonTime()
	{
		return _relicSummonTime;
	}
	
	public void setRelicId(int relicId)
	{
		_relicId = relicId;
	}
	
	public void setReplaceAttemptsUsed(int attempts)
	{
		_replaceAttemptsUsed = attempts;
	}
	
	public void setRelicLevel(int level)
	{
		_relicLevel = level;
	}
	
	public void setRelicCount(int count)
	{
		_relicCount = count;
	}
	
	public void setRelicIndex(int index)
	{
		_relicIndex = index;
	}
	
	public void setRelicSummonTime(long relicSummonTime)
	{
		_relicSummonTime = relicSummonTime;
	}
}
